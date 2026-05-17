<?php

namespace App\Core\Contracts;

use App\Models\Insight;

/**
 * Contrat C-06 : le Insight Engine génère des insights narratifs depuis les KPI agrégés.
 * Jamais d'appel direct au LLM depuis le Core — seule l'interface est connue.
 */
interface InsightEngineInterface
{
    /**
     * Génère un Insight pour le scope donné.
     * Retourne null si les données sont insuffisantes ou si le LLM est indisponible.
     *
     * @param string $scopeType 'vehicle' | 'fleet' | 'organization'
     * @param string $scopeId   UUID du scope
     */
    public function generate(string $scopeType, string $scopeId): ?Insight;
}
