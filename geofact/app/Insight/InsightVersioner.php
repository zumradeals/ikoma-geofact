<?php

namespace App\Insight;

use App\Models\Insight;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Gère la persistance versionnée des insights.
 * INSERT toujours — jamais UPDATE (C-06 : insights immuables).
 */
class InsightVersioner
{
    public function save(
        array  $insightData,
        string $organizationId,
        string $scopeType,
        string $scopeId,
        Carbon $from,
        Carbon $to,
        array  $sourceKpiIds = [],
        array  $sourceEventIds = []
    ): Insight {
        $version = $this->nextVersion($scopeType, $scopeId, $from, $to);

        return Insight::create([
            'id'               => Str::uuid()->toString(),
            'organization_id'  => $organizationId,
            'scope_type'       => $scopeType,
            'scope_id'         => $scopeId,
            'insight_type'     => $insightData['insight_type'],
            'language'         => $insightData['language'] ?? 'fr',
            'insight_text'     => $insightData['insight_text'],
            'confidence_level' => $insightData['confidence_level'],
            'source_kpis'      => $sourceKpiIds,
            'source_events'    => $sourceEventIds,
            'version'          => $version,
            'generated_at'     => now(),
        ]);
    }

    private function nextVersion(string $scopeType, string $scopeId, Carbon $from, Carbon $to): int
    {
        $max = Insight::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->whereDate('generated_at', '>=', $from->toDateString())
            ->whereDate('generated_at', '<=', $to->toDateString())
            ->max('version');

        return $max !== null ? (int) $max + 1 : 1;
    }
}
