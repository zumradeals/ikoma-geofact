<?php

namespace App\Rules\SystemRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Rules\Contracts\RuleInterface;
use Illuminate\Support\Str;

class RS03_HarshBrakingRule implements RuleInterface
{
    public function getRuleId(): string { return 'RS03'; }
    public function getRuleType(): string { return 'RS'; }

    public function applies(CanonicalEvent $event): bool
    {
        return ! empty($event->payload['harsh_braking']);
    }

    public function evaluate(CanonicalEvent $event): ?Alert
    {
        return new Alert([
            'id'              => Str::uuid()->toString(),
            'organization_id' => $event->organizationId,
            'vehicle_id'      => $event->vehicleId,
            'trip_id'         => $event->tripId,
            'rule_id'         => $this->getRuleId(),
            'rule_type'       => $this->getRuleType(),
            'event_type'      => 'alert.harsh.braking',
            'severity'        => 'HIGH',
            'status'          => 'open',
            'triggered_at'    => $event->timestamp,
            'payload'         => [
                'deceleration_g' => $event->payload['deceleration_g'] ?? null,
                'speed_kmh'      => $event->payload['speed_kmh'] ?? null,
                'canonical_id'   => $event->eventId,
            ],
        ]);
    }
}
