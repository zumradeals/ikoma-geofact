<?php

namespace App\Delivery\Channels;

use App\Delivery\Contact;
use App\Models\Alert;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    private const TIMEOUT = 10;

    /**
     * Envoie une Alert via WhatsApp Business API.
     * Retourne bool — jamais d'exception vers DeliveryEngine (C-07).
     */
    public function send(Contact $contact, Alert $alert): bool
    {
        if (empty($contact->phone)) {
            Log::warning('geofact.delivery.whatsapp.no_phone', [
                'contact' => $contact->name,
                'alert_id' => $alert->id,
            ]);
            return false;
        }

        $apiUrl   = config('services.whatsapp.api_url');
        $token    = config('services.whatsapp.token');
        $phoneId  = config('services.whatsapp.phone_number_id');

        if (empty($apiUrl) || empty($token) || empty($phoneId)) {
            Log::warning('geofact.delivery.whatsapp.not_configured');
            return false;
        }

        try {
            $client = new Client(['timeout' => self::TIMEOUT]);

            $client->post("{$apiUrl}/{$phoneId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $contact->phone,
                    'type'              => 'text',
                    'text'              => [
                        'body' => $this->formatMessage($alert),
                    ],
                ],
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::warning('geofact.delivery.whatsapp.send_failed', [
                'alert_id' => $alert->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function formatMessage(Alert $alert): string
    {
        return sprintf(
            "[GEOFACT] %s\nSévérité : %s\nDéclenchée : %s",
            strtoupper($alert->event_type),
            $alert->severity,
            $alert->triggered_at?->format('d/m/Y H:i')
        );
    }
}
