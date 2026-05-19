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

        $totalKm      = $trips->sum('distance_km');
        $totalMinutes = $trips->sum('duration_minutes');

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
            ->whereBetween('ts', [$from->timestamp, $to->timestamp])
            ->avg('speed_kmh');

        $maxSpeed = TelemetryEvent::where('vehicle_id', $vehicleId)
            ->whereNotNull('speed_kmh')
            ->whereBetween('ts', [$from->timestamp, $to->timestamp])
            ->max('speed_kmh');

        $activeDays = $trips->groupBy(fn ($t) => $t->started_at->format('Y-m-d'))->count();

        $lastEvent = TelemetryEvent::where('vehicle_id', $vehicleId)
            ->orderByDesc('ts')
            ->first();

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
            'total_trips'       => $trips->count(),
            'total_km'          => round((float) $totalKm, 1),
            'total_hours'       => round((float) $totalMinutes / 60, 1),
            'avg_speed_kmh'     => $avgSpeed ? round((float) $avgSpeed, 1) : null,
            'max_speed_kmh'     => $maxSpeed ? round((float) $maxSpeed, 1) : null,
            'active_days'       => $activeDays,
            'anomalous_trips'   => $anomalousTrips->count(),
            'anomaly_notes'     => $anomalousTrips->pluck('anomaly_note')->filter()->values()->toArray(),
            'alerts_total'      => $alerts->count(),
            'alerts_critical'   => $alertsBySeverity['CRITICAL'] ?? 0,
            'alerts_high'       => $alertsBySeverity['HIGH'] ?? 0,
            'alerts_medium'     => $alertsBySeverity['MEDIUM'] ?? 0,
            'alerts_low'        => $alertsBySeverity['LOW'] ?? 0,
            'alerts_by_rule'    => $alertsByRule,
            'alerts_unresolved' => $alerts->whereIn('status', ['open', 'triggered', 'delivered'])->count(),
            'last_seen_at'      => $lastEvent ? Carbon::createFromTimestamp($lastEvent->ts)->format('d/m/Y H:i') : '—',
            'trips_detail'      => $trips->map(fn ($t) => [
                'date'     => $t->started_at->format('d/m/Y'),
                'distance' => round((float) $t->distance_km, 1),
                'duration' => round((float) $t->duration_minutes, 0),
                'status'   => $t->status,
            ])->toArray(),
        ];
    }
}
