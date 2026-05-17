<?php

namespace App\Connector\Normalizer;

use Illuminate\Support\Facades\Log;

class FieldNormalizer
{
    public function __construct(private readonly NormalizationMap $map) {}

    /**
     * Traduit les champs fournisseur vers le modèle canonique GEOFACT.
     * Contrat C-10 : le Connector normalise — le Core ne voit que des champs canoniques.
     *
     * Les champs non mappés sont ignorés (pas de contamination du Core).
     * Les champs canoniques déjà présents sont conservés tels quels.
     */
    public function normalize(array $rawPayload, string $providerId): array
    {
        $fieldMap  = $this->map->getMap($providerId);
        $canonical = [];

        foreach ($rawPayload as $sourceField => $value) {
            if (! array_key_exists($sourceField, $fieldMap)) {
                // Champ inconnu pour ce provider — ignoré (jamais transmis au Core)
                continue;
            }

            $targetField = $fieldMap[$sourceField];

            if ($targetField === null) {
                // Champ explicitement exclu dans la map
                continue;
            }

            $canonical[$targetField] = $this->castValue($targetField, $value);
        }

        // Assure que event_type est toujours présent (CCS C-02)
        if (! isset($canonical['event_type']) && isset($rawPayload['event_type'])) {
            $canonical['event_type'] = $rawPayload['event_type'];
        }

        // Assure que device_id est toujours présent (CCS C-02)
        if (! isset($canonical['device_id']) && isset($rawPayload['device_id'])) {
            $canonical['device_id'] = $rawPayload['device_id'];
        }

        Log::debug('geofact.connector.normalizer.normalized', [
            'provider_id'     => $providerId,
            'source_fields'   => array_keys($rawPayload),
            'canonical_fields'=> array_keys($canonical),
        ]);

        return $canonical;
    }

    /**
     * Caste les valeurs vers les types attendus par le modèle canonique.
     */
    private function castValue(string $field, mixed $value): mixed
    {
        return match($field) {
            'speed_kmh', 'latitude', 'longitude', 'altitude_m',
            'fuel_level_pct', 'temperature_celsius'
                => $value !== null ? (float) $value : null,
            'heading'
                => $value !== null ? (int) $value : null,
            'ignition'
                => $value !== null ? (bool) $value : null,
            default
                => $value,
        };
    }
}
