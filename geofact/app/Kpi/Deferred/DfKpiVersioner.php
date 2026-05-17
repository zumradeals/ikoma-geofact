<?php

namespace App\Kpi\Deferred;

use App\Models\KpiRecord;
use Carbon\Carbon;

class DfKpiVersioner
{
    /**
     * Retourne la prochaine version pour un KpiRecord DF.
     * kpi_records est immuable — INSERT toujours, jamais UPDATE (DC-12).
     */
    public function nextVersion(
        string $scopeId,
        string $kpiType,
        Carbon $periodFrom,
        Carbon $periodTo
    ): int {
        $max = KpiRecord::where('scope_id', $kpiType === 'driver_score' ? $scopeId : $scopeId)
            ->where('kpi_type', $kpiType)
            ->where('mode', 'DF')
            ->whereDate('period_from', $periodFrom->toDateString())
            ->whereDate('period_to', $periodTo->toDateString())
            ->max('version');

        return $max !== null ? (int) $max + 1 : 1;
    }
}
