<?php

namespace App\Reports\DataCollectors;

use App\Models\Alert;
use App\Models\Driver;
use App\Models\TelemetryEvent;
use App\Models\Trip;
use Carbon\Carbon;

class DriverDataCollector
{
    public function collect(string $orgId, string $driverId, Carbon $from, Carbon $to): array
    {
        $driver = Driver::where('id', $driverId)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        $trips = Trip::where('driver_id', $driverId)
            ->where('organization_id', $orgId)
            ->whereBetween('started_at', [$from, $to])
            ->whereIn('status', ['completed', 'anomalous'])
            ->with('vehicle')
            ->orderBy('started_at')
            ->get();

        $totalKm      = $trips->sum('distance_km');
        $totalMinutes = $trips->sum('duration_minutes');
        $vehicleIds   = $trips->pluck('vehicle_id')->unique();

        $alerts = Alert::where('organization_id', $orgId)
            ->where('driver_id', $driverId)
            ->whereBetween('triggered_at', [$from, $to])
            ->get();

        $alertsBySeverity = $alerts->groupBy('severity')->map->count()->toArray();
        $alertsByRule     = $alerts->groupBy('rule_id')->map->count()->toArray();

        $avgSpeed = TelemetryEvent::whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('speed_kmh')
            ->where('speed_kmh', '>', 0)
            ->whereBetween('ts', [$from->timestamp, $to->timestamp])
            ->avg('speed_kmh');

        $maxSpeed = TelemetryEvent::whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('speed_kmh')
            ->whereBetween('ts', [$from->timestamp, $to->timestamp])
            ->max('speed_kmh');

        $activeDays     = $trips->groupBy(fn ($t) => $t->started_at->format('Y-m-d'))->count();
        $anomalousTrips = $trips->where('status', 'anomalous');

        $vehiclesUsed = $trips->groupBy('vehicle_id')
            ->map(fn ($ts, $vid) => [
                'plate'    => $ts->first()->vehicle?->plate ?? $vid,
                'trips'    => $ts->count(),
                'distance' => round((float) $ts->sum('distance_km'), 1),
            ])
            ->values()
            ->toArray();

        // Score simplifié : 100 - (alertes critiques*10 + hautes*5 + moyennes*2)
        $rawScore   = 100
            - (($alertsBySeverity['CRITICAL'] ?? 0) * 10)
            - (($alertsBySeverity['HIGH'] ?? 0) * 5)
            - (($alertsBySeverity['MEDIUM'] ?? 0) * 2)
            - ($anomalousTrips->count() * 3);
        $safetyScore = max(0, min(100, $rawScore));

        return [
            'period_from'      => $from->format('d/m/Y'),
            'period_to'        => $to->format('d/m/Y'),
            'driver_name'      => $driver->first_name . ' ' . $driver->last_name,
            'driver_phone'     => $driver->phone ?? '—',
            'license_number'   => $driver->license_number ?? '—',
            'license_expiry'   => $driver->license_expiry?->format('d/m/Y') ?? '—',
            'driver_status'    => $driver->status,
            'total_trips'      => $trips->count(),
            'total_km'         => round((float) $totalKm, 1),
            'total_hours'      => round((float) $totalMinutes / 60, 1),
            'avg_speed_kmh'    => $avgSpeed ? round((float) $avgSpeed, 1) : null,
            'max_speed_kmh'    => $maxSpeed ? round((float) $maxSpeed, 1) : null,
            'active_days'      => $activeDays,
            'anomalous_trips'  => $anomalousTrips->count(),
            'safety_score'     => $safetyScore,
            'vehicles_count'   => $vehicleIds->count(),
            'vehicles_used'    => $vehiclesUsed,
            'alerts_total'     => $alerts->count(),
            'alerts_critical'  => $alertsBySeverity['CRITICAL'] ?? 0,
            'alerts_high'      => $alertsBySeverity['HIGH'] ?? 0,
            'alerts_medium'    => $alertsBySeverity['MEDIUM'] ?? 0,
            'alerts_low'       => $alertsBySeverity['LOW'] ?? 0,
            'alerts_by_rule'   => $alertsByRule,
            'alerts_unresolved'=> $alerts->whereIn('status', ['open', 'triggered', 'delivered'])->count(),
        ];
    }
}
