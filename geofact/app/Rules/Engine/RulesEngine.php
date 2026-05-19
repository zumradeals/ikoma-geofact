<?php

namespace App\Rules\Engine;

use App\Core\Canonical\CanonicalEvent;
use App\Core\Contracts\RulesEngineInterface;
use App\Events\AlertTriggered;
use App\Events\CanonicalEventReceived;
use App\Models\Alert;
use App\Rules\ClientRules\ClientRulesEvaluator;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrateur du Rules Engine — listener de CanonicalEventReceived.
 *
 * Ordre strict C-04.6 :
 * 1. Évaluation RS (SystemRulesEvaluator)
 * 2. Évaluation RMC (ClientRulesEvaluator)
 * 3. Merge — conflit RS vs RMC : RS prime toujours
 * 4. INSERT chaque Alert en DB
 * 5. dispatch(AlertTriggered) — jamais d'appel direct au Delivery Engine
 */
class RulesEngine implements RulesEngineInterface
{
    public function __construct(
        private readonly SystemRulesEvaluator $systemEvaluator,
        private readonly ClientRulesEvaluator $clientEvaluator,
    ) {}

    /**
     * Point d'entrée pour l'Event Listener Laravel.
     */
    public function handle(CanonicalEventReceived $received): void
    {
        try {
            $this->evaluate($received->event);
        } catch (\Throwable $e) {
            Log::error('geofact.rules.system.evaluation_failed', [
                'event_id' => $received->event->eventId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Implémentation RulesEngineInterface (C-04).
     * Le CanonicalEvent n'est JAMAIS modifié — lecture seule.
     *
     * @return Alert[]  Alerts persistées
     */
    public function evaluate(CanonicalEvent $event): array
    {
        // Étape 1 — RS
        $rsAlerts = $this->systemEvaluator->evaluate($event);

        // Étape 2 — RMC
        $rmcAlerts = $this->clientEvaluator->evaluate($event);

        // Étape 3 — Merge : RS prime en cas de conflit sur event_type
        $merged = $this->merge($event->eventId, $rsAlerts, $rmcAlerts);

        // Étapes 4 + 5 — INSERT puis dispatch
        $persisted = [];
        foreach ($merged as $alert) {
            try {
                // Dedup : une seule alerte active par (vehicle_id, rule_id) dans la fenêtre 1h
                $dedupKey = $alert->vehicle_id . '|' . $alert->rule_id;
                $alert->dedup_key = $dedupKey;

                $recentExists = Alert::where('dedup_key', $dedupKey)
                    ->where('triggered_at', '>=', now()->subHour())
                    ->whereIn('status', ['open', 'triggered', 'delivered'])
                    ->exists();

                if ($recentExists) {
                    Log::info('geofact.rules.alert_deduped', [
                        'dedup_key' => $dedupKey,
                        'rule_id'   => $alert->rule_id,
                    ]);
                    continue;
                }

                $alert->save();
                $persisted[] = $alert;
                event(new AlertTriggered($alert));
            } catch (\Throwable $e) {
                Log::error('geofact.rules.alert_persist_failed', [
                    'event_id'   => $event->eventId,
                    'event_type' => $alert->event_type,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('geofact.rules.completed', [
            'event_id'      => $event->eventId,
            'rs_count'      => count($rsAlerts),
            'rmc_count'     => count($rmcAlerts),
            'merged_count'  => count($merged),
            'persisted'     => count($persisted),
        ]);

        return $persisted;
    }

    /**
     * Merge RS + RMC avec priorité RS en cas de conflit sur event_type.
     *
     * @param Alert[] $rsAlerts
     * @param Alert[] $rmcAlerts
     * @return Alert[]
     */
    private function merge(string $eventId, array $rsAlerts, array $rmcAlerts): array
    {
        $rsTypes = array_map(fn(Alert $a) => $a->event_type, $rsAlerts);

        $filteredRmc = [];
        foreach ($rmcAlerts as $rmc) {
            if (in_array($rmc->event_type, $rsTypes, true)) {
                Log::warning('geofact.rules.conflict_rs_rmc', [
                    'event_id'   => $eventId,
                    'event_type' => $rmc->event_type,
                    'resolution' => 'RS_WINS',
                ]);
                continue; // RS prime — RMC ignorée pour ce type
            }
            $filteredRmc[] = $rmc;
        }

        return array_merge($rsAlerts, $filteredRmc);
    }
}
