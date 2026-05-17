<?php
namespace App\Services;
use App\Models\KpiRecord;
use Illuminate\Database\Eloquent\Collection;

class KpiService
{
    public function latest(string $scopeType, string $scopeId, string $organizationId): Collection
    {
        return KpiRecord::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('organization_id', $organizationId)
            ->orderByDesc('computed_at')
            ->limit(20)
            ->get();
    }

    public function history(string $scopeType, string $scopeId, string $organizationId, string $kpiType): Collection
    {
        return KpiRecord::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('organization_id', $organizationId)
            ->where('kpi_type', $kpiType)
            ->orderByDesc('computed_at')
            ->limit(100)
            ->get();
    }
}
