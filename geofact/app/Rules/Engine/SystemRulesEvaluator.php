<?php

namespace App\Rules\Engine;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Rules\SystemRules\RS01_OverspeedRule;
use App\Rules\SystemRules\RS02_SuspiciousStopRule;
use App\Rules\SystemRules\RS03_HarshBrakingRule;
use App\Rules\SystemRules\RS04_GeozoneEntryRule;
use App\Rules\SystemRules\RS05_MaintenanceThresholdRule;
use Illuminate\Support\Facades\Log;

class SystemRulesEvaluator
{
    public function __construct(private RuleConfigResolver $resolver) {}

    /**
     * Évalue les 5 règles système dans l'ordre RS01→RS05.
     * Toutes les RS applicables sont évaluées — pas de court-circuit (C-04.6).
     *
     * @return Alert[]
     */
    public function evaluate(CanonicalEvent $event): array
    {
        $rules = [
            new RS01_OverspeedRule($this->resolver),
            new RS02_SuspiciousStopRule($this->resolver),
            new RS03_HarshBrakingRule(),
            new RS04_GeozoneEntryRule($this->resolver),
            new RS05_MaintenanceThresholdRule(),
        ];

        $alerts = [];

        foreach ($rules as $rule) {
            try {
                if (! $rule->applies($event)) {
                    continue;
                }

                $alert = $rule->evaluate($event);

                if ($alert !== null) {
                    $alerts[] = $alert;
                    Log::info('geofact.rules.system.triggered', [
                        'rule_id'    => $rule->getRuleId(),
                        'event_type' => $alert->event_type,
                        'severity'   => $alert->severity,
                        'event_id'   => $event->eventId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('geofact.rules.system.rule_error', [
                    'rule_id'  => $rule->getRuleId(),
                    'event_id' => $event->eventId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $alerts;
    }
}
