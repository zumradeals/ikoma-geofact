<?php

namespace App\Delivery;

use App\Core\Contracts\DeliveryEngineInterface;
use App\Delivery\Channels\ApiChannel;
use App\Delivery\Channels\EmailChannel;
use App\Delivery\Channels\WhatsAppChannel;
use App\Delivery\Policies\ClientPoliciesEvaluator;
use App\Delivery\Policies\SystemPoliciesEvaluator;
use App\Events\AlertTriggered;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrateur du Delivery Engine — listener de AlertTriggered.
 *
 * Ordre strict C-07 :
 * 1. SystemPoliciesEvaluator (SP — non désactivables)
 * 2. ClientPoliciesEvaluator (CDP)
 * 3. Merge des DeliveryTasks
 * 4. Pour chaque DeliveryTask : livraison + retry x3 + DeliveryLogger
 */
class DeliveryEngine implements DeliveryEngineInterface
{
    private const MAX_RETRIES      = 3;
    private const RETRY_BACKOFF_MS = [1000, 2000, 4000];  // backoff exponentiel en ms

    public function __construct(
        private readonly SystemPoliciesEvaluator $systemPolicies,
        private readonly ClientPoliciesEvaluator $clientPolicies,
        private readonly EmailChannel            $emailChannel,
        private readonly WhatsAppChannel         $whatsAppChannel,
        private readonly ApiChannel              $apiChannel,
        private readonly DeliveryLogger          $logger,
    ) {}

    /**
     * Point d'entrée pour l'Event Listener Laravel.
     */
    public function handle(AlertTriggered $event): void
    {
        $this->deliver($event->alert);
    }

    /**
     * Implémentation DeliveryEngineInterface (C-07).
     */
    public function deliver(Alert $alert): void
    {
        // Étape 1 — SP (toujours en premier)
        $spTasks = $this->systemPolicies->evaluate($alert);

        // Étape 2 — CDP
        $cdpTasks = $this->clientPolicies->evaluate($alert, $alert->organization_id);

        // Étape 3 — Merge (SP + CDP)
        $allTasks = array_merge($spTasks, $cdpTasks);

        if (empty($allTasks)) {
            Log::info('geofact.delivery.no_tasks', [
                'alert_id'   => $alert->id,
                'event_type' => $alert->event_type,
            ]);
            return;
        }

        // Étape 4 — Livraison avec retry
        foreach ($allTasks as $task) {
            $this->dispatchWithRetry($task);
        }
    }

    private function dispatchWithRetry(DeliveryTask $task): void
    {
        $lastError = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                usleep(self::RETRY_BACKOFF_MS[$attempt - 1] * 1000);
            }

            try {
                $success = $this->sendViaChannel($task);

                $this->logger->log($task, $success, $success ? null : 'channel_returned_false');

                if ($success) {
                    return;
                }

                $lastError = 'channel_returned_false';

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $this->logger->log($task, false, $lastError);
            }
        }

        // Échec total après tous les retries
        $this->logger->logFinalFailure($task, $lastError ?? 'unknown_error');
    }

    private function sendViaChannel(DeliveryTask $task): bool
    {
        return match($task->channel) {
            'email'     => $this->emailChannel->send($task->contact, $task->alert),
            'whatsapp'  => $this->whatsAppChannel->send($task->contact, $task->alert),
            'api'       => $this->apiChannel->send($task->contact, $task->alert),
            default     => throw new \InvalidArgumentException("Canal inconnu : {$task->channel}"),
        };
    }
}
