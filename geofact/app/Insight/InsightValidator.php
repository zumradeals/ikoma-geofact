<?php

namespace App\Insight;

use App\Exceptions\CanonicalValidationException;

class InsightValidator
{
    // Portées valides C-06.2
    private const VALID_SCOPE_TYPES = ['event', 'trip', 'vehicle', 'driver', 'fleet', 'organization'];
    private const VALID_INSIGHT_TYPES = ['anomaly', 'trend', 'performance', 'alert', 'summary'];
    private const VALID_CONFIDENCE = ['high', 'medium', 'low'];

    /**
     * @throws CanonicalValidationException si les données sont invalides
     */
    public function validate(array $insightData): bool
    {
        $scopeType    = $insightData['scope_type'] ?? null;
        $scopeId      = $insightData['scope_id'] ?? null;
        $insightText  = $insightData['insight_text'] ?? null;
        $insightType  = $insightData['insight_type'] ?? null;
        $confidence   = $insightData['confidence_level'] ?? null;

        if (! in_array($scopeType, self::VALID_SCOPE_TYPES, true)) {
            throw new CanonicalValidationException(
                "scope_type [{$scopeType}] invalide — liste fermée C-06.2."
            );
        }

        if (empty($scopeId)) {
            throw new CanonicalValidationException('scope_id ne peut pas être vide.');
        }

        if (empty($insightText)) {
            throw new CanonicalValidationException('insight_text ne peut pas être vide.');
        }

        if (! in_array($insightType, self::VALID_INSIGHT_TYPES, true)) {
            throw new CanonicalValidationException(
                "insight_type [{$insightType}] invalide."
            );
        }

        if (! in_array($confidence, self::VALID_CONFIDENCE, true)) {
            throw new CanonicalValidationException(
                "confidence_level [{$confidence}] invalide."
            );
        }

        return true;
    }
}
