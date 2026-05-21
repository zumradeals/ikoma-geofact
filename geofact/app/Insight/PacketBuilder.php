<?php

namespace App\Insight;

use App\Insight\KnowledgeBase\BenchmarkBuilder;
use App\Insight\KnowledgeBase\MemoryBuilder;
use App\Insight\KnowledgeBase\TrendBuilder;
use App\Models\Alert;
use App\Models\KpiRecord;
use App\Models\Trip;
use Carbon\Carbon;

/**
 * Construit le paquet structuré envoyé à l'IA — IKOMA Intelligence.
 *
 * Contrat C-06 : jamais de données brutes (raw_store) transmises à l'IA.
 *
 * Le paquet contient :
 * - Données de la période courante (KPIs, alertes, trajets)
 * - Mémoire des insights précédents (MemoryBuilder)
 * - Tendances historiques 7/30/90 jours (TrendBuilder)
 * - Positionnement dans la flotte (BenchmarkBuilder)
 */
class PacketBuilder
{
    public function __construct(
        private readonly MemoryBuilder    $memory,
        private readonly TrendBuilder     $trends,
        private readonly BenchmarkBuilder $benchmark,
    ) {}

    public function build(
        string $scopeType,
        string $scopeId,
        string $organizationId,
        Carbon $from,
        Carbon $to
    ): array {
        $kpis   = $this->loadKpis($scopeType, $scopeId, $from, $to);
        $alerts = $this->loadAlerts($scopeType, $scopeId, $organizationId, $from, $to);
        $trips  = $this->loadTripStats($scopeType, $scopeId, $organizationId, $from, $to);

        // IKOMA Intelligence — couches de connaissance
        $memory    = $this->memory->build($scopeType, $scopeId);
        $trends    = $this->trends->build($scopeType, $scopeId, $organizationId);
        $benchmark = $this->benchmark->build($scopeType, $scopeId, $organizationId);

        $lastPosition = $this->loadLastPosition($scopeType, $scopeId);

        return [
            'scope_type'       => $scopeType,
            'scope_id'         => $scopeId,
            'organization_id'  => $organizationId,
            'period_from'      => $from->toIso8601String(),
            'period_to'        => $to->toIso8601String(),

            // Données courantes
            'kpis'             => $kpis,
            'alerts'           => $alerts,
            'trips'            => $trips,
            'last_position'    => $lastPosition,

            // Intelligence layers
            'memory'           => $memory,
            'trends'           => $trends,
            'benchmark'        => $benchmark,

            'source_kpi_ids'   => array_column($kpis, 'id'),
            'source_event_ids' => array_column($alerts, 'id'),
        ];
    }

    private function loadKpis(string $scopeType, string $scopeId, Carbon $from, Carbon $to): array
    {
        return KpiRecord::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->whereBetween('computed_at', [$from, $to])
            ->orderByDesc('computed_at')
            ->limit(50)
            ->get(['id', 'kpi_type', 'value', 'unit', 'mode', 'computed_at'])
            ->toArray();
    }

    private function loadAlerts(
        string $scopeType,
        string $scopeId,
        string $organizationId,
        Carbon $from,
        Carbon $to
    ): array {
        $query = Alert::where('organization_id', $organizationId)
            ->whereBetween('triggered_at', [$from, $to])
            ->orderByDesc('triggered_at')
            ->limit(30);

        match($scopeType) {
            'vehicle' => $query->where('vehicle_id', $scopeId),
            'driver'  => $query->where('driver_id', $scopeId),
            'fleet'   => $query->where('fleet_id', $scopeId),
            default   => null,
        };

        return $query->get(['id', 'event_type', 'severity', 'status', 'triggered_at'])
            ->toArray();
    }

    private function loadLastPosition(string $scopeType, string $scopeId): ?array
    {
        if ($scopeType !== 'vehicle') {
            return null;
        }

        $event = \App\Models\TelemetryEvent::where('vehicle_id', $scopeId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('ts')
            ->first(['latitude', 'longitude', 'speed_kmh', 'ignition', 'ts']);

        if (! $event) {
            return null;
        }

        return [
            'latitude'    => $event->latitude,
            'longitude'   => $event->longitude,
            'speed_kmh'   => $event->speed_kmh,
            'ignition'    => $event->ignition,
            'recorded_at' => $event->ts?->toIso8601String(),
        ];
    }

    private function loadTripStats(
        string $scopeType,
        string $scopeId,
        string $organizationId,
        Carbon $from,
        Carbon $to
    ): array {
        $query = Trip::where('organization_id', $organizationId)
            ->whereIn('status', ['completed', 'anomalous'])
            ->whereBetween('started_at', [$from, $to]);

        match($scopeType) {
            'vehicle' => $query->where('vehicle_id', $scopeId),
            'driver'  => $query->where('driver_id', $scopeId),
            'fleet'   => $query->where('fleet_id', $scopeId),
            default   => null,
        };

        $trips = $query->get(['distance_km', 'duration_minutes', 'status', 'started_at']);

        if ($trips->isEmpty()) {
            return ['total' => 0];
        }

        return [
            'total'            => $trips->count(),
            'completed'        => $trips->where('status', 'completed')->count(),
            'anomalous'        => $trips->where('status', 'anomalous')->count(),
            'total_km'         => round((float) $trips->sum('distance_km'), 1),
            'avg_km_per_trip'  => round((float) $trips->avg('distance_km'), 1),
            'avg_duration_min' => round((float) $trips->avg('duration_minutes'), 0),
        ];
    }
}
