<?php

namespace App\Insight;

use App\Exceptions\CanonicalValidationException;

class InsightValidator
{
    private const VALID_SCOPE_TYPES    = ['event', 'trip', 'vehicle', 'driver', 'fleet', 'organization'];
    private const VALID_INSIGHT_TYPES  = ['anomaly', 'trend', 'performance', 'alert', 'summary'];
    private const VALID_CONFIDENCE     = ['high', 'medium', 'low'];
    private const VALID_TREND_DIR      = ['improving', 'stable', 'degrading'];
    private const VALID_FLEET_POSITION = [
        'top_quartile', 'above_average', 'average',
        'below_average', 'bottom_quartile', 'insufficient_data',
    ];

    /**
     * @throws CanonicalValidationException si les champs obligatoires sont invalides
     */
    public function validate(array $insightData): bool
    {
        $scopeType   = $insightData['scope_type']    ?? null;
        $scopeId     = $insightData['scope_id']      ?? null;
        $insightText = $insightData['insight_text']  ?? null;
        $insightType = $insightData['insight_type']  ?? null;
        $confidence  = $insightData['confidence_level'] ?? null;

        if (! in_array($scopeType, self::VALID_SCOPE_TYPES, true)) {
            throw new CanonicalValidationException("scope_type [{$scopeType}] invalide.");
        }
        if (empty($scopeId)) {
            throw new CanonicalValidationException('scope_id ne peut pas être vide.');
        }
        if (empty($insightText)) {
            throw new CanonicalValidationException('insight_text ne peut pas être vide.');
        }
        if (! in_array($insightType, self::VALID_INSIGHT_TYPES, true)) {
            throw new CanonicalValidationException("insight_type [{$insightType}] invalide.");
        }
        if (! in_array($confidence, self::VALID_CONFIDENCE, true)) {
            throw new CanonicalValidationException("confidence_level [{$confidence}] invalide.");
        }

        // Champs enrichis — validation souple (l'IA peut ne pas les retourner)
        $trendDir = $insightData['trend_direction'] ?? null;
        if ($trendDir !== null && ! in_array($trendDir, self::VALID_TREND_DIR, true)) {
            throw new CanonicalValidationException("trend_direction [{$trendDir}] invalide.");
        }

        $riskScore = $insightData['risk_score'] ?? null;
        if ($riskScore !== null && (! is_numeric($riskScore) || $riskScore < 0 || $riskScore > 10)) {
            throw new CanonicalValidationException("risk_score doit être entre 0 et 10.");
        }

        $fleetPos = $insightData['fleet_position'] ?? null;
        if ($fleetPos !== null && ! in_array($fleetPos, self::VALID_FLEET_POSITION, true)) {
            // Non bloquant — l'IA peut retourner une valeur inattendue
            $insightData['fleet_position'] = 'insufficient_data';
        }

        return true;
    }
}
