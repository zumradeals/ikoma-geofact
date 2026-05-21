<?php

namespace App\Console\Commands;

use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cloture manuellement les trajets orphelins.
 *
 * Un trajet orphelin est un trajet actif, ancien, et sans signal GPS recent.
 */
class TripCloseOrphansCommand extends Command
{
    protected $signature = 'trips:close-orphans
                            {--dry-run : Afficher les trajets orphelins sans les cloturer}
                            {--yes : Ne pas demander de confirmation}
                            {--force : Cloturer selon l\'age du trajet, meme si le dernier signal GPS est recent}
                            {--threshold=360 : Age en minutes au-dela duquel un trajet est orphelin}';

    protected $description = 'Cloture les trajets actifs depuis plus de N minutes sans signal GPS recent (defaut : 360 = 6h)';

    public function handle(): int
    {
        $thresholdMinutes = max(1, (int) $this->option('threshold'));
        $isDryRun         = (bool) $this->option('dry-run');
        $skipConfirmation = (bool) $this->option('yes');
        $isForce          = (bool) $this->option('force');
        $cutoff           = Carbon::now()->subMinutes($thresholdMinutes);

        $candidates = Trip::where('status', 'active')
            ->where('started_at', '<=', $cutoff)
            ->get();

        $orphans = $candidates
            ->filter(function (Trip $trip) use ($cutoff, $isForce): bool {
                if ($isForce) {
                    return true;
                }

                $lastEvent = $this->latestPositionEvent($trip);

                return ! $lastEvent || Carbon::parse($lastEvent->ts)->lte($cutoff);
            })
            ->values();

        if ($orphans->isEmpty()) {
            if ($candidates->isNotEmpty()) {
                $this->info("Aucun trajet orphelin : {$candidates->count()} trajet(s) actif(s) ancien(s), mais avec signal GPS recent.");
                return self::SUCCESS;
            }

            $this->info("Aucun trajet orphelin trouve (seuil : {$thresholdMinutes} min).");
            return self::SUCCESS;
        }

        $this->warn("Trajets orphelins trouves : {$orphans->count()}");

        $headers = ['ID', 'Vehicle ID', 'Demarre le', 'Age (h)', 'Dernier signal', 'Silence (h)'];
        $rows = $orphans->map(function (Trip $trip) {
            $lastEvent = $this->latestPositionEvent($trip);

            return [
                $trip->id,
                $trip->vehicle_id,
                $trip->started_at,
                round(Carbon::parse($trip->started_at)->diffInMinutes(now()) / 60, 1),
                $lastEvent?->ts ?? 'aucun',
                $lastEvent ? round(Carbon::parse($lastEvent->ts)->diffInMinutes(now()) / 60, 1) : 'aucun',
            ];
        })->toArray();

        $this->table($headers, $rows);

        if ($isDryRun) {
            $this->info('Mode dry-run - aucune modification effectuee.');
            return self::SUCCESS;
        }

        if (! $skipConfirmation && ! $this->confirm("Cloturer ces {$orphans->count()} trajet(s) comme anomalous ?")) {
            $this->info('Annule.');
            return self::SUCCESS;
        }

        $closed = 0;

        foreach ($orphans as $trip) {
            try {
                $lastEvent = $this->latestPositionEvent($trip);
                $startedAt = Carbon::parse($trip->started_at);
                $endedAtAt = $lastEvent ? Carbon::parse($lastEvent->ts) : now();

                if ($endedAtAt->lt($startedAt)) {
                    $endedAtAt = now();
                }

                $endedAt = $endedAtAt->toDateTimeString();

                $coords = DB::table('telemetry_events')
                    ->where('organization_id', $trip->organization_id)
                    ->where('vehicle_id', $trip->vehicle_id)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('ts', [$trip->started_at, $endedAt])
                    ->orderBy('ts')
                    ->get(['latitude', 'longitude']);

                $distanceKm      = $this->calculateDistance($coords);
                $durationMinutes = max(1, (int) $startedAt->diffInMinutes($endedAtAt));

                $trip->update([
                    'status'           => 'anomalous',
                    'ended_at'         => $endedAt,
                    'end_latitude'     => $lastEvent?->latitude,
                    'end_longitude'    => $lastEvent?->longitude,
                    'distance_km'      => round($distanceKm, 3),
                    'duration_minutes' => $durationMinutes,
                    'anomaly_note'     => 'Trajet cloture automatiquement - absence de signal > 6h',
                ]);

                $closed++;

                Log::warning('geofact.trip_detector.closed_orphan_manual', [
                    'trip_id'        => $trip->id,
                    'vehicle_id'     => $trip->vehicle_id,
                    'trip_age_h'     => round(Carbon::parse($trip->started_at)->diffInMinutes(now()) / 60, 1),
                    'last_signal_at' => $lastEvent?->ts,
                ]);
            } catch (\Throwable $e) {
                $this->error("Erreur sur le trajet {$trip->id} : {$e->getMessage()}");
                Log::error('geofact.trip_detector.close_orphan_failed', [
                    'trip_id' => $trip->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->info("{$closed} trajet(s) cloture(s).");

        return self::SUCCESS;
    }

    private function latestPositionEvent(Trip $trip): ?object
    {
        return DB::table('telemetry_events')
            ->where('organization_id', $trip->organization_id)
            ->where('vehicle_id', $trip->vehicle_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('ts')
            ->first(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);
    }

    private function calculateDistance(\Illuminate\Support\Collection $coords): float
    {
        $total = 0.0;
        $prev  = null;

        foreach ($coords as $point) {
            if ($prev !== null) {
                $total += $this->haversine(
                    (float) $prev->latitude,
                    (float) $prev->longitude,
                    (float) $point->latitude,
                    (float) $point->longitude,
                );
            }
            $prev = $point;
        }

        return $total;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * asin(sqrt($a));
    }
}
