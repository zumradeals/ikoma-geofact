<?php

namespace App\Delivery\Channels;

use App\Delivery\Contact;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailChannel
{
    /**
     * Envoie une Alert par email via le Mailer Laravel.
     * Retourne bool — jamais d'exception vers DeliveryEngine (C-07).
     */
    public function send(Contact $contact, Alert $alert): bool
    {
        if (empty($contact->email)) {
            Log::warning('geofact.delivery.email.no_address', [
                'contact'  => $contact->name,
                'alert_id' => $alert->id,
            ]);
            return false;
        }

        try {
            Mail::html(
                $this->buildHtml($alert),
                function ($message) use ($contact, $alert) {
                    $message->to($contact->email, $contact->name)
                            ->subject($this->buildSubject($alert));
                }
            );

            return true;

        } catch (\Throwable $e) {
            Log::warning('geofact.delivery.email.send_failed', [
                'alert_id' => $alert->id,
                'to'       => $contact->email,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function buildSubject(Alert $alert): string
    {
        return sprintf('[GEOFACT %s] %s', $alert->severity, strtoupper($alert->event_type));
    }

    private function buildHtml(Alert $alert): string
    {
        $severityColor = match($alert->severity) {
            'CRITICAL' => '#d32f2f',
            'HIGH'     => '#f57c00',
            'MEDIUM'   => '#fbc02d',
            default    => '#388e3c',
        };

        return sprintf(
            '<div style="font-family:sans-serif;max-width:600px">
              <h2 style="color:%s">Alerte GEOFACT — %s</h2>
              <table style="width:100%%">
                <tr><td><strong>Type</strong></td><td>%s</td></tr>
                <tr><td><strong>Sévérité</strong></td><td>%s</td></tr>
                <tr><td><strong>Statut</strong></td><td>%s</td></tr>
                <tr><td><strong>Déclenchée</strong></td><td>%s</td></tr>
              </table>
            </div>',
            $severityColor,
            strtoupper($alert->event_type),
            $alert->event_type,
            $alert->severity,
            $alert->status,
            $alert->triggered_at?->format('d/m/Y H:i:s') ?? '-'
        );
    }
}
