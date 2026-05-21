<?php

namespace App\Reports\DataCollectors;

use App\Models\Alert;
use App\Models\TelemetryEvent;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;

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

        // Km et heures calculés sur les trajets complétés uniquement
        // Les trajets anomaleux (0 km, moteur tournant à l'arrêt) fausseraient les KPIs
        $completedTrips = $trips->where('status', 'completed');
        $totalKm        = $completedTrips->sum('distance_km');
        $totalMinutes   = $completedTrips->sum('duration_minutes');

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
            ->whereNotNull('speed_kmh')
            ->where('speed_kmh', '>', 0)
            ->whereBetween('ts', [$from, $to])
            ->avg('speed_kmh');

        $maxSpeed = TelemetryEvent::where('vehicle_id', $vehicleId)
            ->whereNotNull('speed_kmh')
            ->whereBetween('ts', [$from, $to])
            ->max('speed_kmh');

        $activeDays = $completedTrips->groupBy(fn ($t) => $t->started_at->format('Y-m-d'))->count();

        $lastEvent = TelemetryEvent::where('vehicle_id', $vehicleId)
            ->orderByDesc('ts')
            ->first(['ts', 'latitude', 'longitude', 'speed_kmh', 'ignition']);

        $anomalousTrips = $trips->where('status', 'anomalous');

        return [
            'period_from'       => $from->format('d/m/Y'),
            'period_to'         => $to->format('d/m/Y'),
            'vehicle_plate'     => $vehicle->plate,
            'vehicle_brand'     => $vehicle->brand ?? '—',
            'vehicle_model'     => $vehicle->model ?? '—',
            'vehicle_year'      => $vehicle->year ?? '—',
            'fleet_name'        => $vehicle->fleet?->name ?? 'Sans flotte',
            'vehicle_status'    => $vehicle->status,
            'total_trips'       => $completedTrips->count(),
            'total_km'          => $totalKm > 0 ? round((float) $totalKm, 1) : null,
            'total_hours'       => $totalMinutes > 0 ? round((float) $totalMinutes / 60, 1) : null,
            'avg_speed_kmh'     => $avgSpeed ? round((float) $avgSpeed, 1) : null,
            'max_speed_kmh'     => ($maxSpeed && $maxSpeed > 0) ? round((float) $maxSpeed, 1) : null,
            'active_days'       => $activeDays,
            'total_trips_all'   => $trips->count(),
            'anomalous_trips'   => $anomalousTrips->count(),
            'anomaly_notes'     => $anomalousTrips->pluck('anomaly_note')->filter()->values()->toArray(),
            'alerts_total'      => $alerts->count(),
            'alerts_critical'   => $alertsBySeverity['CRITICAL'] ?? 0,
            'alerts_high'       => $alertsBySeverity['HIGH'] ?? 0,
            'alerts_medium'     => $alertsBySeverity['MEDIUM'] ?? 0,
            'alerts_low'        => $alertsBySeverity['LOW'] ?? 0,
            'alerts_by_rule'    => $alertsByRule,
            'alerts_unresolved' => $alerts->whereIn('status', ['open', 'triggered', 'delivered'])->count(),
            'last_seen_at'        => $lastEvent?->ts?->format('d/m/Y H:i') ?? '—',
            'last_seen_latitude'  => $lastEvent?->latitude,
            'last_seen_longitude' => $lastEvent?->longitude,
            'last_seen_speed_kmh' => $lastEvent?->speed_kmh,
            'last_seen_ignition'  => $lastEvent?->ignition,
            'trips_detail'      => $trips->map(fn ($t) => [
                'date'     => $t->started_at->format('d/m/Y'),
                'distance' => round((float) $t->distance_km, 1),
                'duration' => round((float) $t->duration_minutes, 0),
                'status'   => $t->status,
            ])->toArray(),
        ];
    }
}
