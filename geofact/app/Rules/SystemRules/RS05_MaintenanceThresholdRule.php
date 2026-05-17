<?php

namespace App\Rules\SystemRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Rules\Contracts\RuleInterface;
use Illuminate\Support\Str;

class RS05_MaintenanceThresholdRule implements RuleInterface
{
    // Seuil kilométrique déclenchant la maintenance — défaut 10 000 km
    private int $thresholdKm;

    public function __construct(int $thresholdKm = 10000)
    {
        $this->thresholdKm = $thresholdKm;
    }

    public function getRuleId(): string { return 'RS05'; }
    public function getRuleType(): string { return 'RS'; }

    public function applies(CanonicalEvent $event): bool
    {
        return isset($event->payload['odometer_km']) || isset($event->payload['distance_km']);
    }

    public function evaluate(CanonicalEvent $event): ?Alert
    {
        $distanceKm = (float) ($event->payload['odometer_km'] ?? $event->payload['distance_km'] ?? 0);

        if ($distanceKm < $this->thresholdKm) {
            return null;
        }

        return new Alert([
            'id'              => Str::uuid()->toString(),
            'organization_id' => $event->organizationId,
            'vehicle_id'      => $event->vehicleId,
            'trip_id'         => $event->tripId,
            'rule_id'         => $this->getRuleId(),
            'rule_type'       => $this->getRuleType(),
            'event_type'      => 'maintenance.threshold.reached',
            'severity'        => 'MEDIUM',
            'status'          => 'open',
            'triggered_at'    => $event->timestamp,
            'payload'         => [
                'distance_km'   => $distanceKm,
                'threshold_km'  => $this->thresholdKm,
                'canonical_id'  => $event->eventId,
            ],
        ]);
    }
}
