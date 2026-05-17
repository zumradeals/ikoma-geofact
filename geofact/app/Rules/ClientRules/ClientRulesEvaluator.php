<?php

namespace App\Rules\ClientRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;

class ClientRulesEvaluator
{
    public function __construct(private readonly RmcRuleLoader $loader) {}

    /**
     * Évalue les règles RMC actives du client.
     * En cas d'absence de table client_rules (DB non migrée), retourne [] sans exception.
     *
     * @return Alert[]
     */
    public function evaluate(CanonicalEvent $event): array
    {
        try {
            $rules = $this->loader->load($event->organizationId);
        } catch (\Throwable $e) {
            // Table client_rules absente ou inaccessible — non bloquant
            Log::warning('geofact.rules.rmc.loader_unavailable', [
                'organization_id' => $event->organizationId,
                'error'           => $e->getMessage(),
            ]);
            return [];
        }

        $alerts = [];

        foreach ($rules as $rule) {
            try {
                if (! $rule->applies($event)) {
                    continue;
                }

                $alert = $rule->evaluate($event);

                if ($alert !== null) {
                    $alert->rule_type = 'RMC';
                    $alerts[]         = $alert;

                    Log::info('geofact.rules.rmc.triggered', [
                        'rule_id'    => $rule->getRuleId(),
                        'event_type' => $alert->event_type,
                        'event_id'   => $event->eventId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('geofact.rules.rmc.evaluation_failed', [
                    'rule_id'  => $rule->getRuleId(),
                    'event_id' => $event->eventId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $alerts;
    }
}
