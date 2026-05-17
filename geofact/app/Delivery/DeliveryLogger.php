<?php

namespace App\Delivery;

use Illuminate\Support\Facades\Log;

/**
 * Aucune livraison n'est silencieuse — tout résultat est loggué (C-07).
 */
class DeliveryLogger
{
    public function log(DeliveryTask $task, bool $success, ?string $error = null): void
    {
        $context = [
            'policy_code' => $task->policyCode,
            'channel'     => $task->channel,
            'alert_id'    => $task->alert->id,
            'event_type'  => $task->alert->event_type,
            'severity'    => $task->alert->severity,
            'recipient'   => $task->contact->name,
        ];

        if ($success) {
            Log::info('geofact.delivery.sent', $context);
        } else {
            Log::warning('geofact.delivery.failed', array_merge($context, [
                'error' => $error,
            ]));
        }
    }

    public function logFinalFailure(DeliveryTask $task, string $error): void
    {
        Log::error('geofact.system.delivery.failed', [
            'policy_code' => $task->policyCode,
            'channel'     => $task->channel,
            'alert_id'    => $task->alert->id,
            'error'       => $error,
        ]);
    }
}
