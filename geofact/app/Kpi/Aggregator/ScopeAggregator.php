<?php

namespace App\Kpi\Aggregator;

use App\Models\Fleet;
use App\Models\KpiRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Agrège les KPI du bas vers le haut : Vehicle → Fleet → Organization (C-05).
 * INSERT uniquement dans kpi_records — jamais UPDATE (DC-12).
 */
class ScopeAggregator
{
    public function aggregateToFleet(string $fleetId, string $kpiType, Carbon $period): void
    {
        $fleet = Fleet::find($fleetId);
        if (! $fleet) {
            Log::warning('geofact.kpi.aggregator.fleet_not_found', ['fleet_id' => $fleetId]);
            return;
        }

        // Collecte les dernières valeurs RT par vehicle dans cette flotte
        $vehicleIds = $fleet->vehicles()->pluck('id');

        $avg = KpiRecord::whereIn('scope_id', $vehicleIds)
            ->where('kpi_type', $kpiType)
            ->where('mode', 'RT')
            ->whereDate('computed_at', $period->toDateString())
            ->avg('value');

        if ($avg === null) {
            return;
        }

        $this->insertAggregated('fleet', $fleetId, $fleet->organization_id, $kpiType, $avg, $period);
    }

    public function aggregateToOrganization(string $orgId, string $kpiType, Carbon $period): void
    {
        $fleetIds = Fleet::where('organization_id', $orgId)->pluck('id');

        $avg = KpiRecord::whereIn('scope_id', $fleetIds)
            ->where('kpi_type', $kpiType)
            ->where('mode', 'RT')
            ->whereDate('computed_at', $period->toDateString())
            ->avg('value');

        if ($avg === null) {
            return;
        }

        $this->insertAggregated('organization', $orgId, $orgId, $kpiType, $avg, $period);
    }

    private function insertAggregated(
        string $scopeType,
        string $scopeId,
        string $organizationId,
        string $kpiType,
        float  $value,
        Carbon $period
    ): void {
        try {
            KpiRecord::create([
                'id'              => Str::uuid()->toString(),
                'organization_id' => $organizationId,
                'scope_type'      => $scopeType,
                'scope_id'        => $scopeId,
                'kpi_type'        => $kpiType . '_aggregated',
                'mode'            => 'RT',
                'period_from'     => $period->copy()->startOfDay(),
                'period_to'       => $period->copy()->endOfDay(),
                'value'           => round($value, 4),
                'unit'            => null,
                'version'         => 1,
                'computed_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.kpi.aggregator.insert_failed', [
                'scope_type' => $scopeType,
                'scope_id'   => $scopeId,
                'kpi_type'   => $kpiType,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
