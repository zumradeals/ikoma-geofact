<?php

namespace App\Delivery\Policies;

use App\Delivery\Contact;
use App\Delivery\DeliveryTask;
use App\Models\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Évalue les Client Distribution Policies (CDP).
 * Chargées depuis la DB — filtrées par event_type, severity, horaires.
 * Évaluées après les SP — jamais avant (C-07.1).
 */
class ClientPoliciesEvaluator
{
    /** @return DeliveryTask[] */
    public function evaluate(Alert $alert, string $organizationId): array
    {
        try {
            $policies = DB::table('delivery_policies')
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->get();
        } catch (\Throwable $e) {
            // Table absente ou inaccessible — non bloquant, log seulement
            Log::warning('geofact.delivery.cdp.unavailable', [
                'organization_id' => $organizationId,
                'error'           => $e->getMessage(),
            ]);
            return [];
        }

        $tasks = [];

        foreach ($policies as $policy) {
            if (! $this->policyMatches($policy, $alert)) {
                continue;
            }

            $contact = $this->buildContact($policy);
            if ($contact === null) {
                continue;
            }

            $channels = json_decode($policy->channels ?? '["email"]', true);
            foreach ($channels as $channel) {
                $tasks[] = new DeliveryTask($alert, $contact, $channel, 'CDP-' . $policy->id);
            }
        }

        return $tasks;
    }

    private function policyMatches(object $policy, Alert $alert): bool
    {
        // Filtre event_type (wildcard supporté : 'alert.*')
        $eventTypePattern = $policy->event_type_filter ?? null;
        if ($eventTypePattern && ! $this->matchesPattern($alert->event_type, $eventTypePattern)) {
            return false;
        }

        // Filtre severity minimum
        $minSeverity = $policy->min_severity ?? null;
        if ($minSeverity && ! $this->severityMeetsMinimum($alert->severity, $minSeverity)) {
            return false;
        }

        // Filtre horaires (active_from / active_to en HH:MM)
        $activeFrom = $policy->active_from ?? null;
        $activeTo   = $policy->active_to   ?? null;
        if ($activeFrom && $activeTo) {
            $nowTime = now()->format('H:i');
            if ($nowTime < $activeFrom || $nowTime > $activeTo) {
                return false;
            }
        }

        return true;
    }

    private function matchesPattern(string $eventType, string $pattern): bool
    {
        if (str_ends_with($pattern, '.*')) {
            return str_starts_with($eventType, rtrim($pattern, '.*') . '.');
        }
        return $eventType === $pattern;
    }

    private function severityMeetsMinimum(string $severity, string $minimum): bool
    {
        $levels = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3, 'CRITICAL' => 4];
        return ($levels[$severity] ?? 0) >= ($levels[$minimum] ?? 0);
    }

    private function buildContact(object $policy): ?Contact
    {
        $email      = $policy->recipient_email      ?? null;
        $phone      = $policy->recipient_phone      ?? null;
        $webhookUrl = $policy->recipient_webhook_url ?? null;

        if (empty($email) && empty($phone) && empty($webhookUrl)) {
            return null;
        }

        return new Contact(
            name:       $policy->recipient_name ?? 'Destinataire',
            email:      $email,
            phone:      $phone,
            webhookUrl: $webhookUrl,
        );
    }
}
