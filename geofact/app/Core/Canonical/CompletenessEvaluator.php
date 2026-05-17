<?php

namespace App\Core\Canonical;

use Illuminate\Support\Facades\Log;

class CompletenessEvaluator
{
    /**
     * Champs Critiques Système (CCS) — définis par GEOFACT, non désactivables (C-02.2).
     * L'absence d'un seul CCS → REJECTED.
     */
    private const CCS_FIELDS = ['timestamp', 'device_id', 'event_type'];

    /**
     * Évalue la complétude d'un payload normalisé.
     * Contrat C-02 : CCS > CCM dans la hiérarchie de criticité.
     *
     * @return string COMPLETE | INCOMPLETE | REJECTED
     */
    public function evaluate(array $payload, string $organizationId): array
    {
        $missingFields = [];

        // Vérification CCS — rejet immédiat si l'un est absent
        foreach (self::CCS_FIELDS as $field) {
            if (empty($payload[$field])) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            Log::warning('geofact.completeness.rejected_ccs_missing', [
                'organization_id' => $organizationId,
                'missing_ccs'     => $missingFields,
            ]);

            return [
                'completeness'  => 'REJECTED',
                'missing_fields'=> $missingFields,
            ];
        }

        // Vérification CCM — incomplet si CCM manquant, mais transmis au Core avec réserve
        $missingCcm = $this->evaluateCcm($payload, $organizationId);

        if (! empty($missingCcm)) {
            Log::info('geofact.completeness.incomplete_ccm_missing', [
                'organization_id' => $organizationId,
                'missing_ccm'     => $missingCcm,
            ]);

            return [
                'completeness'   => 'INCOMPLETE',
                'missing_fields' => $missingCcm,
            ];
        }

        return [
            'completeness'   => 'COMPLETE',
            'missing_fields' => [],
        ];
    }

    /**
     * Charge et vérifie les CCM du client depuis sa configuration.
     * En v1 : les CCM sont stockés en configuration (extensible en DB à partir de P-06+).
     */
    private function evaluateCcm(array $payload, string $organizationId): array
    {
        // En v1 : les CCM sont définis dans la configuration par secteur.
        // La configuration CCM par organization sera chargée depuis la DB en P-06.
        // Ici on retourne vide — aucun CCM manquant par défaut.
        return [];
    }
}
