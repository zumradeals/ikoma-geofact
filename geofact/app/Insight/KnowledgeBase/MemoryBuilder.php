<?php

namespace App\Insight\KnowledgeBase;

use App\Models\Insight;

/**
 * Charge les derniers insights d'un scope pour donner une mémoire à l'IA.
 * L'IA peut ainsi comparer l'état actuel avec les analyses précédentes
 * et détecter une progression ou une régression dans le temps.
 */
class MemoryBuilder
{
    private const MAX_MEMORY = 5;

    public function build(string $scopeType, string $scopeId): array
    {
        $previous = Insight::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->orderByDesc('generated_at')
            ->limit(self::MAX_MEMORY)
            ->get([
                'version', 'generated_at', 'insight_type', 'confidence_level',
                'trend_direction', 'risk_score', 'insight_text',
            ]);

        if ($previous->isEmpty()) {
            return ['count' => 0, 'history' => []];
        }

        return [
            'count'   => $previous->count(),
            'history' => $previous->map(fn ($i) => [
                'version'         => $i->version,
                'generated_at'    => $i->generated_at?->toDateString(),
                'insight_type'    => $i->insight_type,
                'confidence'      => $i->confidence_level,
                'trend_direction' => $i->trend_direction,
                'risk_score'      => $i->risk_score,
                'summary'         => mb_substr($i->insight_text, 0, 300),
            ])->toArray(),
        ];
    }
}
