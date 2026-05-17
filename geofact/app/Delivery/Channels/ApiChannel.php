<?php

namespace App\Delivery\Channels;

use App\Delivery\Contact;
use App\Models\Alert;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ApiChannel
{
    private const TIMEOUT = 15;

    /**
     * Envoie une Alert en POST JSON vers un webhook client.
     * Retourne bool — jamais d'exception vers DeliveryEngine (C-07).
     */
    public function send(Contact $contact, Alert $alert): bool
    {
        if (empty($contact->webhookUrl)) {
            Log::warning('geofact.delivery.api.no_webhook', [
                'contact'  => $contact->name,
                'alert_id' => $alert->id,
            ]);
            return false;
        }

        try {
            $client = new Client(['timeout' => self::TIMEOUT]);

            $response = $client->post($contact->webhookUrl, [
                'json' => [
                    'alert_id'    => $alert->id,
                    'event_type'  => $alert->event_type,
                    'severity'    => $alert->severity,
                    'status'      => $alert->status,
                    'triggered_at'=> $alert->triggered_at?->toIso8601String(),
                    'payload'     => $alert->payload,
                ],
            ]);

            return $response->getStatusCode() < 400;

        } catch (\Throwable $e) {
            Log::warning('geofact.delivery.api.send_failed', [
                'alert_id'    => $alert->id,
                'webhook_url' => $contact->webhookUrl,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }
}
