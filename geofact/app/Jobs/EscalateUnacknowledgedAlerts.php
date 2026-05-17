<?php

namespace App\Jobs;

use App\Delivery\Contact;
use App\Delivery\DeliveryEngine;
use App\Delivery\DeliveryTask;
use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Escalade les Alerts livrées mais non-acquittées depuis plus du délai configuré.
 * Déclenché par le Scheduler (C-07).
 */
class EscalateUnacknowledgedAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Délai d'escalade par défaut : 2 heures
    private const DEFAULT_DELAY_HOURS = 2;

    public function handle(DeliveryEngine $deliveryEngine): void
    {
        $delayHours = config('geofact.escalation_delay_hours', self::DEFAULT_DELAY_HOURS);
        $threshold  = now()->subHours($delayHours);

        $alerts = Alert::where('status', 'open')
            ->whereNull('acknowledged_at')
            ->where('triggered_at', '<=', $threshold)
            ->whereIn('severity', ['HIGH', 'CRITICAL'])
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        Log::info('geofact.delivery.escalation.started', ['count' => $alerts->count()]);

        foreach ($alerts as $alert) {
            $this->escalateAlert($alert, $deliveryEngine);
        }

        Log::info('geofact.delivery.escalation.completed', ['escalated' => $alerts->count()]);
    }

    private function escalateAlert(Alert $alert, DeliveryEngine $deliveryEngine): void
    {
        try {
            // Passage du statut en escalated (DC-09 : resolution_note non requise pour escalation)
            Alert::withoutEvents(function () use ($alert) {
                $alert->status       = 'escalated';
                $alert->escalated_at = now();
                $alert->save();
            });

            // Livraison vers le superviseur configuré
            $supervisorEmail = env('GEOFACT_SUPERVISOR_EMAIL');
            $supervisorPhone = env('GEOFACT_SUPERVISOR_PHONE');

            if (empty($supervisorEmail) && empty($supervisorPhone)) {
                Log::warning('geofact.delivery.escalation.no_supervisor', [
                    'alert_id' => $alert->id,
                ]);
                return;
            }

            $supervisor = new Contact(
                name:  'Superviseur GEOFACT',
                email: $supervisorEmail,
                phone: $supervisorPhone,
            );

            $task = new DeliveryTask($alert, $supervisor, 'email', 'ESCALATION');
            $deliveryEngine->deliver($alert);

            Log::info('geofact.delivery.escalation.alert_escalated', [
                'alert_id'   => $alert->id,
                'event_type' => $alert->event_type,
            ]);

        } catch (\Throwable $e) {
            Log::error('geofact.delivery.escalation.failed', [
                'alert_id' => $alert->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
