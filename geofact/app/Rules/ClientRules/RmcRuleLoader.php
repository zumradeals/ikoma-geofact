<?php

namespace App\Rules\ClientRules;

use App\Rules\Contracts\RuleInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RmcRuleLoader
{
    /**
     * Charge les règles RMC actives d'un client depuis la DB.
     * Les RMC sont stockées dans la table client_rules (Mode A = paramétrage, Mode B = composition).
     * Option C (création libre) est refusée en v1 (C-04.3).
     *
     * @return RuleInterface[]
     */
    public function load(string $organizationId): array
    {
        // La table client_rules est gérée par les admins GEOFACT (C-04.5).
        // En v1, seul le Mode A (paramétrage de RS existantes) est supporté.
        $rows = DB::table('client_rules')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->get();

        $rules = [];

        foreach ($rows as $row) {
            try {
                $rule = $this->buildRule($row);
                if ($rule !== null) {
                    $rules[] = $rule;
                }
            } catch (\Throwable $e) {
                Log::warning('geofact.rules.rmc.load_failed', [
                    'organization_id' => $organizationId,
                    'rule_row_id'     => $row->id ?? null,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return $rules;
    }

    private function buildRule(object $row): ?RuleInterface
    {
        $config = json_decode($row->config ?? '{}', true);

        // Mode A uniquement — paramétrage d'une règle système existante
        return match($row->base_rule_id ?? null) {
            'RS01' => new \App\Rules\SystemRules\RS01_OverspeedRule(
                thresholdKmh: (int) ($config['threshold_kmh'] ?? 90)
            ),
            'RS02' => new \App\Rules\SystemRules\RS02_SuspiciousStopRule(
                thresholdHours: (int) ($config['threshold_hours'] ?? 4)
            ),
            'RS05' => new \App\Rules\SystemRules\RS05_MaintenanceThresholdRule(
                thresholdKm: (int) ($config['threshold_km'] ?? 10000)
            ),
            default => null,
        };
    }
}
