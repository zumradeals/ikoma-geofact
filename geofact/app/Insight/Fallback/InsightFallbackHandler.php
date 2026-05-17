<?php

namespace App\Insight\Fallback;

use Illuminate\Support\Facades\Log;

/**
 * Gestionnaire de fallback : l'IA est indisponible ou a retourné une réponse invalide.
 * Contrat C-06 : le Core ne dépend jamais de la disponibilité de l'IA — null retourné, jamais d'exception.
 */
class InsightFallbackHandler
{
    public function handle(
        string     $scopeType,
        string     $scopeId,
        string     $reason,
        ?\Throwable $exception = null
    ): null {
        Log::warning('geofact.insight.generation.failed', array_filter([
            'scope_type' => $scopeType,
            'scope_id'   => $scopeId,
            'reason'     => $reason,
            'error'      => $exception?->getMessage(),
        ]));

        // Émet l'événement système pour traçabilité (C-09)
        event('system.insight.generation.failed');

        return null;
    }
}
