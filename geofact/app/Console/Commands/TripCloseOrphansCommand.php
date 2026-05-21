<?php

namespace App\Console\Commands;

use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clôture manuellement les trajets orphelins (actifs depuis plus de 6 heures).
 *
 * Usage :
 *   php artisan trips:close-orphans
 *   php artisan trips:close-orphans --dry-run
 *   php artisan trips:close-orphans --threshold=360
 */
class TripCloseOrphansCommand extends Command
{
    protected $signature = 'trips:close-orphans
                            {--dry-run : Afficher les trajets orphelins sans les clôturer}
                            {--threshold=360 : Âge en minutes au-delà duquel un trajet est orphelin}';

    protected $description = 'Clôture les trajets actifs depuis plus de N minutes (défaut : 360 = 6h)';

    public function handle(): int
    {
        $thresholdMinutes = (int) $this->option('threshold');
        $isDryRun         = (bool) $this->option('dry-run');
        $cutoff           = Carbon::now()->subMinutes($thresholdMinutes);

        $orphans = Trip::where('status', 'active')
            ->where('started_at', '<=', $cutoff)
            ->get();

        if ($orphans->isEmpty()) {
            $this->info("Aucun trajet orphelin trouvé (seuil : {$thresholdMinutes} min).");
            return self::SUCCESS;
        }

        $this->warn("Trajets orphelins trouvés : {$orphans->count()}");

        $headers = ['ID', 'Vehicle ID', 'Démarré le', 'Âge (h)'];
        $rows    = $orphans->map(fn ($t) => [
            $t->id,
            $t->vehicle_id,
            $t->started_at,
            round(Carbon::parse($t->started_at)->diffInMinutes(now()) / 60, 1),
        ])->toArray();

        $this->table($headers, $rows);

        if ($isDryRun) {
            $this->info('Mode dry-run — aucune modification effectuée.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Clôturer ces {$orphans->count()} trajet(s) comme anomalous ?")) {
            $this->info('Annulé.');
            return self::SUCCESS;
        }

        $closed = 0;

        foreach ($orphans as $trip) {
            try {
                $lastEvent = DB::table('telemetry_events')
                    ->where('vehicle_id', $trip->vehicle_id)
                    ->whereNotNull('latitude')
                    ->orderByDesc('ts')
                    ->first(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);

                $endedAt = $lastEvent?->ts ?? now()->toDateTimeString();

                $coords = DB::table('telemetry_events')
                    ->where('vehicle_id', $trip->vehicle_id)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('ts', [$trip->started_at, $endedAt])
                    ->orderBy('ts')
                    ->get(['latitude', 'longitude']);

                $distanceKm      = $this->calculateDistance($coords);
                $durationMinutes = max(1, (int) Carbon::parse($trip->started_at)->diffInMinutes(Carbon::parse($endedAt)));

                $trip->update([
                    'status'           => 'anomalous',
                    'ended_at'         => $endedAt,
                    'end_latitude'     => $lastEvent?->latitude,
                    'end_longitude'    => $lastEvent?->longitude,
                    'distance_km'      => round($distanceKm, 3),
                    'duration_minutes' => $durationMinutes,
                    'anomaly_note'     => 'Trajet clôturé automatiquement — absence de signal > 6h',
                ]);

                $closed++;

                Log::warning('geofact.trip_detector.closed_orphan_manual', [
                    'trip_id'    => $trip->id,
                    'vehicle_id' => $trip->vehicle_id,
                    'trip_age_h' => round($durationMinutes / 60, 1),
                ]);
            } catch (\Throwable $e) {
                $this->error("Erreur sur le trajet {$trip->id} : {$e->getMessage()}");
                Log::error('geofact.trip_detector.close_orphan_failed', [
                    'trip_id' => $trip->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->info("{$closed} trajet(s) clôturé(s).");

        return self::SUCCESS;
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
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * asin(sqrt($a));
    }
}
