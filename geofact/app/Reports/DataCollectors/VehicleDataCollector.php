<?php

namespace App\Reports\DataCollectors;

use App\Models\Alert;
use App\Models\TelemetryEvent;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleCurrentPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleDataCollector
{
    public function collect(string $orgId, string $vehicleId, Carbon $from, Carbon $to): array
    {
        $vehicle = Vehicle::where('id', $vehicleId)
            ->where('organization_id', $orgId)
            ->with('fleet')
            ->firstOrFail();

        $trips = Trip::where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->whereBetween('started_at', [$from, $to])
            ->whereIn('status', ['completed', 'anomalous'])
            ->orderBy('started_at')
            ->get();

        $completedTrips = $trips->where('status', 'completed');
        $totalKm        = $completedTrips->sum('distance_km');
        $totalMinutes   = $completedTrips->sum('duration_minutes');

        // Fallback télémétrie brute si aucun trip complété sur la période.
        // Cela couvre le cas où le TripDetectorJob n'a pas encore tourné
        // (cron absent ou retard de scheduler) mais que des TelemetryEvents existent.
        $usingTelemetryFallback = false;
        if ($completedTrips->isEmpty()) {
            $telemetry = $this->estimateFromTelemetry($vehicleId, $orgId, $from, $to);
            if ($telemetry['has_data']) {
                $usingTelemetryFallback = true;
                $totalKm      = $telemetry['total_km'];
                $totalMinutes = $telemetry['total_minutes'];
            }
        }

        $alerts = Alert::where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->whereBetween('triggered_at', [$from, $to])
            ->get();

        $alertsByRule = $alerts->groupBy('rule_id')
            ->map->count()
            ->toArray();

        $alertsBySeverity = $alerts->groupBy('severity')
            ->map->count()
            ->toArray();

        $avgSpeed = TelemetryEvent::where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->whereNotNull('speed_kmh')
            ->where('speed_kmh', '>', 0)
            ->whereBetween('ts', [$from, $to])
            ->avg('speed_kmh');

        $maxSpeed = TelemetryEvent::where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->whereNotNull('speed_kmh')
            ->whereBetween('ts', [$from, $to])
            ->max('speed_kmh');

        $activeDays = $usingTelemetryFallback
            ? ($telemetry['active_days'] ?? 0)
            : $completedTrips->groupBy(fn ($t) => $t->started_at->format('Y-m-d'))->count();

        $lastPosition = VehicleCurrentPosition::where('organization_id', $orgId)
            ->where('vehicle_id', $vehicleId)
            ->first(['position_ts', 'latitude', 'longitude', 'speed_kmh', 'ignition', 'freshness_status', 'source_status']);

        $anomalousTrips = $trips->where('status', 'anomalous');

        $activityNote = $usingTelemetryFallback
            ? 'Données estimées depuis la télémétrie brute — segmentation en trajets en attente (TripDetector)'
            : 'Trajets officiels IKOMA segmentés';

        return [
            'period_from'         => $from->format('d/m/Y'),
            'period_to'           => $to->format('d/m/Y'),
            'vehicle_plate'       => $vehicle->plate,
            'vehicle_brand'       => $vehicle->brand ?? '-',
            'vehicle_model'       => $vehicle->model ?? '-',
            'vehicle_year'        => $vehicle->year ?? '-',
            'fleet_name'          => $vehicle->fleet?->name ?? 'Sans flotte',
            'vehicle_status'      => $vehicle->status,
            'activity_note'       => $activityNote,
            'total_trips'         => $usingTelemetryFallback ? ($telemetry['estimated_trips'] ?? 0) : $completedTrips->count(),
            'total_km'            => $totalKm > 0 ? round((float) $totalKm, 1) : null,
            'total_hours'         => $totalMinutes > 0 ? round((float) $totalMinutes / 60, 1) : null,
            'avg_speed_kmh'       => $avgSpeed ? round((float) $avgSpeed, 1) : null,
            'max_speed_kmh'       => ($maxSpeed && $maxSpeed > 0) ? round((float) $maxSpeed, 1) : null,
            'active_days'         => $activeDays,
            'total_trips_all'     => $trips->count(),
            'anomalous_trips'     => $anomalousTrips->count(),
            'anomaly_notes'       => $anomalousTrips->pluck('anomaly_note')->filter()->values()->toArray(),
            'alerts_total'        => $alerts->count(),
            'alerts_critical'     => $alertsBySeverity['CRITICAL'] ?? 0,
            'alerts_high'         => $alertsBySeverity['HIGH'] ?? 0,
            'alerts_medium'       => $alertsBySeverity['MEDIUM'] ?? 0,
            'alerts_low'          => $alertsBySeverity['LOW'] ?? 0,
            'alerts_by_rule'      => $alertsByRule,
            'alerts_unresolved'   => $alerts->whereIn('status', ['open', 'triggered', 'delivered'])->count(),
            'last_seen_at'        => $lastPosition?->position_ts?->format('d/m/Y H:i') ?? '-',
            'last_seen_latitude'  => $lastPosition?->latitude,
            'last_seen_longitude' => $lastPosition?->longitude,
            'last_seen_speed_kmh' => $lastPosition?->speed_kmh,
            'last_seen_ignition'  => $lastPosition?->ignition,
            'last_seen_freshness' => $lastPosition?->live_freshness_status,
            'last_seen_source'    => $lastPosition?->source_status,
            'trips_detail'        => $trips->map(fn ($t) => [
                'date'     => $t->started_at->format('d/m/Y'),
                'distance' => round((float) $t->distance_km, 1),
                'duration' => round((float) $t->duration_minutes, 0),
                'status'   => $t->status,
            ])->toArray(),
        ];
    }

    /**
     * Estime les métriques d'activité depuis telemetry_events quand la table
     * trips est vide (TripDetector pas encore passé ou cron absent).
     */
    private function estimateFromTelemetry(string $vehicleId, string $orgId, Carbon $from, Carbon $to): array
    {
        $events = DB::table('telemetry_events')
            ->where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('ts', [$from, $to])
            ->orderBy('ts')
            ->get(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);

        if ($events->isEmpty()) {
            return ['has_data' => false, 'total_km' => null, 'total_minutes' => null, 'active_days' => 0, 'estimated_trips' => 0];
        }

        // Distance haversine totale entre points consécutifs
        $totalKm = 0.0;
        $prev    = null;
        foreach ($events as $e) {
            if ($prev) {
                $totalKm += $this->haversine(
                    (float) $prev->latitude, (float) $prev->longitude,
                    (float) $e->latitude,    (float) $e->longitude
                );
            }
            $prev = $e;
        }

        // Minutes en mouvement (segments consécutifs < 30 min d'écart)
        $totalMinutes = 0;
        $prev         = null;
        foreach ($events as $e) {
            if ($prev) {
                $gapMin = Carbon::parse($prev->ts)->diffInMinutes(Carbon::parse($e->ts));
                if ($gapMin <= 30 && ((float) $e->speed_kmh > 0 || $e->ignition == 1)) {
                    $totalMinutes += $gapMin;
                }
            }
            $prev = $e;
        }

        // Jours distincts avec au moins un signal de mouvement
        $activeDays = $events
            ->filter(fn ($e) => ((float) $e->speed_kmh > 0 || $e->ignition == 1))
            ->map(fn ($e)    => Carbon::parse($e->ts)->format('Y-m-d'))
            ->unique()
            ->count();

        // Estimation du nombre de trajets : segments séparés par > 5 min d'arrêt
        $estimatedTrips = 0;
        $inTrip         = false;
        $stopStart      = null;
        foreach ($events as $e) {
            $moving = ((float) $e->speed_kmh > 0 || $e->ignition == 1);
            if ($moving && ! $inTrip) {
                $estimatedTrips++;
                $inTrip    = true;
                $stopStart = null;
            } elseif (! $moving && $inTrip) {
                if ($stopStart === null) {
                    $stopStart = Carbon::parse($e->ts);
                } elseif ($stopStart->diffInMinutes(Carbon::parse($e->ts)) >= 5) {
                    $inTrip    = false;
                    $stopStart = null;
                }
            } elseif ($moving) {
                $stopStart = null;
            }
        }

        return [
            'has_data'        => true,
            'total_km'        => $totalKm > 0.0 ? round($totalKm, 1) : null,
            'total_minutes'   => $totalMinutes > 0 ? $totalMinutes : null,
            'active_days'     => $activeDays,
            'estimated_trips' => $estimatedTrips,
        ];
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
