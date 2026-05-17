<?php

namespace App\Insight;

use App\Models\Alert;
use App\Models\KpiRecord;
use Carbon\Carbon;

/**
 * Construit le paquet structuré envoyé à l'IA.
 * Contrat C-06 : jamais de données brutes (raw_store) transmises à l'IA.
 */
class PacketBuilder
{
    public function build(
        string $scopeType,
        string $scopeId,
        string $organizationId,
        Carbon $from,
        Carbon $to
    ): array {
        $kpis   = $this->loadKpis($scopeType, $scopeId, $from, $to);
        $alerts = $this->loadAlerts($scopeType, $scopeId, $organizationId, $from, $to);

        return [
            'scope_type'      => $scopeType,
            'scope_id'        => $scopeId,
            'organization_id' => $organizationId,
            'period_from'     => $from->toIso8601String(),
            'period_to'       => $to->toIso8601String(),
            'kpis'            => $kpis,
            'alerts'          => $alerts,
            'source_kpi_ids'  => array_column($kpis, 'id'),
            'source_event_ids'=> array_column($alerts, 'id'),
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

        // Filtre selon le niveau de scope
        match($scopeType) {
            'vehicle' => $query->where('vehicle_id', $scopeId),
            'driver'  => $query->where('driver_id', $scopeId),
            'fleet'   => $query->where('fleet_id', $scopeId),
            default   => null,
        };

        return $query->get(['id', 'event_type', 'severity', 'status', 'triggered_at'])
            ->toArray();
    }
}
