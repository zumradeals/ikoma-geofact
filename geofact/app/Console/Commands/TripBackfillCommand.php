<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reconstitue les trajets historiques depuis telemetry_events.
 *
 * Usage :
 *   php artisan trips:backfill --from=2026-05-13 --to=2026-05-20
 *   php artisan trips:backfill --vehicle=3950KV01 --from=2026-05-13 --to=2026-05-20
 *   php artisan trips:backfill --from=2026-05-13 --to=2026-05-20 --force
 */
class TripBackfillCommand extends Command
{
    protected $signature = 'trips:backfill
                            {--from=          : Date de début (Y-m-d), défaut = hier}
                            {--to=            : Date de fin   (Y-m-d), défaut = aujourd\'hui}
                            {--vehicle=       : Plaque ou UUID du véhicule (optionnel — tous si absent)}
                            {--org=           : UUID de l\'organisation (optionnel — toutes si absent)}
                            {--force          : Recréer même si des trajets existent déjà sur la période}
                            {--dry-run        : Simuler sans écrire en base}';

    protected $description = 'Reconstitue les trajets historiques depuis telemetry_events';

    // Paramètres de détection (identiques à TripDetectorJob)
    private const STOP_THRESHOLD_MINUTES  = 5;
    private const OFFLINE_THRESHOLD_MINUTES = 30;
    private const MIN_TRIP_DISTANCE_KM    = 0.1;
    private const CHUNK_HOURS             = 6; // traiter par tranches de 6h pour limiter la RAM

    public function handle(): int
    {
        $from  = Carbon::parse($this->option('from') ?? now()->subDay()->format('Y-m-d'))->startOfDay();
        $to    = Carbon::parse($this->option('to')   ?? now()->format('Y-m-d'))->endOfDay();
        $force = $this->option('force');
        $dry   = $this->option('dry-run');

        $this->info("Backfill trajets : {$from->format('d/m/Y')} → {$to->format('d/m/Y')}" . ($dry ? ' [DRY-RUN]' : ''));

        $query = Vehicle::where('status', 'active');

        if ($orgId = $this->option('org')) {
            $query->where('organization_id', $orgId);
        }

        if ($plate = $this->option('vehicle')) {
            $query->where(function ($q) use ($plate) {
                $q->where('plate', $plate)->orWhere('id', $plate);
            });
        }

        $vehicles = $query->get();
        $this->info("Véhicules à traiter : {$vehicles->count()}");

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($vehicles as $vehicle) {
            [$created, $skipped] = $this->backfillVehicle($vehicle, $from, $to, $force, $dry);
            $totalCreated += $created;
            $totalSkipped += $skipped;
            $this->line("  {$vehicle->plate} — {$created} trajet(s) créé(s), {$skipped} ignoré(s)");
        }

        $this->info("Terminé. Total créés : {$totalCreated}, ignorés : {$totalSkipped}");
        return self::SUCCESS;
    }

    private function backfillVehicle(Vehicle $vehicle, Carbon $from, Carbon $to, bool $force, bool $dry): array
    {
        // Si pas --force et qu'il existe déjà des trajets sur la période → skip
        if (! $force) {
            $existing = Trip::where('vehicle_id', $vehicle->id)
                ->whereBetween('started_at', [$from, $to])
                ->count();
            if ($existing > 0) {
                return [0, $existing];
            }
        }

        // Charge tous les events GPS du véhicule sur la période, par ordre chronologique
        $events = DB::table('telemetry_events')
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('ts', [$from, $to])
            ->orderBy('ts')
            ->get(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);

        if ($events->isEmpty()) {
            return [0, 0];
        }

        return $this->runStateMachine($vehicle, $events, $dry);
    }

    private function runStateMachine(Vehicle $vehicle, \Illuminate\Support\Collection $events, bool $dry): array
    {
        $created = 0;

        $state           = 'IDLE';
        $tripStartEvent  = null;
        $tripPoints      = [];
        $lastMovementTs  = null;
        $lastMovementIdx = 0;

        $eventsList = $events->values()->all();
        $count      = count($eventsList);

        for ($i = 0; $i < $count; $i++) {
            $e = $eventsList[$i];
            $ts = Carbon::parse($e->ts);
            $isMoving = ($e->speed_kmh > 0) || ($e->ignition == 1);

            if ($state === 'IDLE') {
                if ($isMoving) {
                    $state           = 'MOVING';
                    $tripStartEvent  = $e;
                    $tripPoints      = [$e];
                    $lastMovementTs  = $ts;
                    $lastMovementIdx = $i;
                }
                continue;
            }

            // state === MOVING
            $tripPoints[] = $e;

            if ($isMoving) {
                $lastMovementTs  = $ts;
                $lastMovementIdx = $i;
            }

            // Silence GPS — fermer si trop long sans event
            $minutesSinceLast = $ts->diffInMinutes(Carbon::parse($eventsList[$i - 1]->ts ?? $e->ts));
            if ($minutesSinceLast >= self::OFFLINE_THRESHOLD_MINUTES) {
                // Clore sur le dernier event avec mouvement
                $closeEvent = $eventsList[$lastMovementIdx];
                $pointsToClose = array_slice($tripPoints, 0, $lastMovementIdx - array_search($tripStartEvent, $tripPoints) + 1);
                if ($this->createTrip($vehicle, $tripStartEvent, $closeEvent, collect($pointsToClose), 'completed', $dry)) {
                    $created++;
                }
                $state = 'IDLE';
                $tripStartEvent = null;
                $tripPoints = [];
                $lastMovementTs = null;
                continue;
            }

            // Stop détecté : assez de temps sans mouvement ?
            $minutesStopped = $lastMovementTs ? $ts->diffInMinutes($lastMovementTs) : 0;
            if ($minutesStopped >= self::STOP_THRESHOLD_MINUTES) {
                $closeEvent = $eventsList[$lastMovementIdx];
                // Collect points up to lastMovement
                $startOffset = $tripStartEvent ? array_search($tripStartEvent, $eventsList) : 0;
                $pointsSlice = array_slice($eventsList, (int)$startOffset, $lastMovementIdx - (int)$startOffset + 1);
                if ($this->createTrip($vehicle, $tripStartEvent, $closeEvent, collect($pointsSlice), 'completed', $dry)) {
                    $created++;
                }
                $state = 'IDLE';
                $tripStartEvent = null;
                $tripPoints = [];
                $lastMovementTs = null;
            }
        }

        // Fermer un trajet encore ouvert en fin de période
        if ($state === 'MOVING' && $tripStartEvent && $lastMovementTs) {
            $closeEvent = $eventsList[$lastMovementIdx];
            $startOffset = array_search($tripStartEvent, $eventsList) ?: 0;
            $pointsSlice = array_slice($eventsList, (int)$startOffset, $lastMovementIdx - (int)$startOffset + 1);
            if ($this->createTrip($vehicle, $tripStartEvent, $closeEvent, collect($pointsSlice), 'completed', $dry)) {
                $created++;
            }
        }

        return [$created, 0];
    }

    private function createTrip(Vehicle $vehicle, object $startEvent, object $endEvent, \Illuminate\Support\Collection $points, string $status, bool $dry): bool
    {
        $distanceKm      = $this->calculateDistance($points);
        $startedAt       = Carbon::parse($startEvent->ts);
        $endedAt         = Carbon::parse($endEvent->ts);
        $durationMinutes = max(1, (int) $startedAt->diffInMinutes($endedAt));

        $isMicro = $distanceKm < self::MIN_TRIP_DISTANCE_KM;
        if ($isMicro) {
            $status = 'anomalous';
        }

        if ($dry) {
            $this->line(sprintf(
                '    [DRY] %s → %s  %.3f km  %d min  %s',
                $startedAt->format('d/m H:i'),
                $endedAt->format('d/m H:i'),
                $distanceKm,
                $durationMinutes,
                $isMicro ? 'ANOMALOUS' : 'OK'
            ));
            return ! $isMicro || true; // compte quand même en dry
        }

        Trip::create([
            'id'               => Str::uuid()->toString(),
            'vehicle_id'       => $vehicle->id,
            'driver_id'        => null,
            'organization_id'  => $vehicle->organization_id,
            'fleet_id'         => $vehicle->fleet_id,
            'status'           => $status,
            'started_at'       => $startedAt,
            'ended_at'         => $endedAt,
            'start_latitude'   => $startEvent->latitude,
            'start_longitude'  => $startEvent->longitude,
            'end_latitude'     => $endEvent->latitude,
            'end_longitude'    => $endEvent->longitude,
            'distance_km'      => round($distanceKm, 3),
            'duration_minutes' => $durationMinutes,
            'anomaly_note'     => $isMicro ? 'micro_trip:distance_below_threshold' : null,
        ]);

        Log::info('geofact.trip_backfill.created', [
            'vehicle_id'  => $vehicle->id,
            'started_at'  => $startedAt->toISOString(),
            'ended_at'    => $endedAt->toISOString(),
            'distance_km' => round($distanceKm, 3),
            'status'      => $status,
        ]);

        return true;
    }

    private function calculateDistance(\Illuminate\Support\Collection $points): float
    {
        $total = 0.0;
        $prev  = null;
        foreach ($points as $p) {
            if ($prev !== null) {
                $total += $this->haversine(
                    (float) $prev->latitude,  (float) $prev->longitude,
                    (float) $p->latitude,     (float) $p->longitude,
                );
            }
            $prev = $p;
        }
        return $total;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * asin(sqrt($a));
    }
}
