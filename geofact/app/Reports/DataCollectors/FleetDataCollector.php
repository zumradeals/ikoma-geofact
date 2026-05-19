<?php

namespace App\Reports\DataCollectors;

use App\Models\Alert;
use App\Models\TelemetryEvent;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FleetDataCollector
{
    public function collect(string $orgId, Carbon $from, Carbon $to): array
    {
        $vehicles = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->with('fleet')
            ->get();

        $vehicleIds = $vehicles->pluck('id');

        // Véhicules localisés (au moins 1 event GPS sur la période)
        $localizedIds = TelemetryEvent::where('organization_id', $orgId)
            ->whereNotNull('latitude')
            ->whereBetween('ts', [$from->timestamp, $to->timestamp])
            ->distinct()
            ->pluck('vehicle_id');

        // Trajets sur la période
        $trips = Trip::where('organization_id', $orgId)
            ->whereBetween('started_at', [$from, $to])
            ->whereIn('status', ['completed', 'anomalous'])
            ->get();

        $totalKm       = $trips->sum('distance_km');
        $totalMinutes  = $trips->sum('duration_minutes');
        $anomalousTrips = $trips->where('status', 'anomalous')->count();

        // Véhicule le plus actif
        $topVehicle = $trips->groupBy('vehicle_id')
            ->map(fn ($t) => $t->sum('distance_km'))
            ->sortDesc()
            ->first();

        $topVehicleId = $trips->groupBy('vehicle_id')
            ->map(fn ($t) => $t->sum('distance_km'))
            ->sortDesc()
            ->keys()
            ->first();

        $topVehicleName = $topVehicleId
            ? ($vehicles->firstWhere('id', $topVehicleId)?->plate ?? '—')
            : '—';

        // Alertes sur la période
        $alerts = Alert::where('organization_id', $orgId)
            ->whereBetween('triggered_at', [$from, $to])
            ->whereIn('vehicle_id', $vehicleIds)
            ->get();

        $alertsBySeverity = $alerts->groupBy('severity')
            ->map->count()
            ->toArray();

        $unresolvedAlerts = $alerts->whereIn('status', ['open', 'triggered', 'delivered', 'escalated'])->count();

        // Vitesse moyenne (événements avec vitesse)
        $avgSpeed = TelemetryEvent::where('organization_id', $orgId)
            ->whereNotNull('speed_kmh')
            ->where('speed_kmh', '>', 0)
            ->whereBetween('ts', [$from->timestamp, $to->timestamp])
            ->avg('speed_kmh');

        // KPI records DF existants pour la période
        $kpis = DB::table('kpi_records')
            ->where('organization_id', $orgId)
            ->where('scope_type', 'organization')
            ->where('mode', 'DF')
            ->whereBetween('period_from', [$from, $to])
            ->get()
            ->groupBy('kpi_type')
            ->map(fn ($r) => $r->sortByDesc('computed_at')->first()?->value);

        // Répartition par flotte
        $byFleet = $vehicles->groupBy(fn ($v) => $v->fleet?->name ?? 'Sans flotte')
            ->map(fn ($vs) => [
                'count'     => $vs->count(),
                'localized' => $vs->filter(fn ($v) => $localizedIds->contains($v->id))->count(),
            ]);

        return [
            'period_from'       => $from->format('d/m/Y'),
            'period_to'         => $to->format('d/m/Y'),
            'total_vehicles'    => $vehicles->count(),
            'localized_count'   => $localizedIds->count(),
            'coverage_pct'      => $vehicles->count() > 0
                ? round($localizedIds->count() / $vehicles->count() * 100, 1)
                : 0,
            'total_trips'       => $trips->count(),
            'total_km'          => round((float) $totalKm, 1),
            'total_hours'       => round((float) $totalMinutes / 60, 1),
            'anomalous_trips'   => $anomalousTrips,
            'top_vehicle'       => $topVehicleName,
            'top_vehicle_km'    => round((float) $topVehicle, 1),
            'avg_speed_kmh'     => $avgSpeed ? round((float) $avgSpeed, 1) : null,
            'alerts_total'      => $alerts->count(),
            'alerts_critical'   => $alertsBySeverity['CRITICAL'] ?? 0,
            'alerts_high'       => $alertsBySeverity['HIGH'] ?? 0,
            'alerts_medium'     => $alertsBySeverity['MEDIUM'] ?? 0,
            'alerts_low'        => $alertsBySeverity['LOW'] ?? 0,
            'alerts_unresolved' => $unresolvedAlerts,
            'kpis'              => $kpis->toArray(),
            'by_fleet'          => $byFleet->toArray(),
        ];
    }
}
