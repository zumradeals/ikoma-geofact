<?php

namespace App\Rules\SystemRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Rules\Contracts\RuleInterface;
use Illuminate\Support\Str;

class RS01_OverspeedRule implements RuleInterface
{
    // Seuil par défaut — paramétrable via config ou RMC Mode A
    private int $thresholdKmh;

    public function __construct(int $thresholdKmh = 90)
    {
        $this->thresholdKmh = $thresholdKmh;
    }

    public function getRuleId(): string { return 'RS01'; }
    public function getRuleType(): string { return 'RS'; }

    public function applies(CanonicalEvent $event): bool
    {
        return isset($event->payload['speed_kmh']);
    }

    public function evaluate(CanonicalEvent $event): ?Alert
    {
        $speed = (float) ($event->payload['speed_kmh'] ?? 0);

        if ($speed <= $this->thresholdKmh) {
            return null;
        }

        $excess   = $speed - $this->thresholdKmh;
        $pct      = ($excess / $this->thresholdKmh) * 100;
        $severity = match(true) {
            $pct < 20  => 'MEDIUM',
            $pct < 40  => 'HIGH',
            default    => 'CRITICAL',
        };

        return new Alert([
            'id'              => Str::uuid()->toString(),
            'organization_id' => $event->organizationId,
            'vehicle_id'      => $event->vehicleId,
            'trip_id'         => $event->tripId,
            'rule_id'         => $this->getRuleId(),
            'rule_type'       => $this->getRuleType(),
            'event_type'      => 'alert.overspeed.detected',
            'severity'        => $severity,
            'status'          => 'open',
            'triggered_at'    => $event->timestamp,
            'payload'         => [
                'speed_kmh'      => $speed,
                'threshold_kmh'  => $this->thresholdKmh,
                'excess_pct'     => round($pct, 2),
                'canonical_id'   => $event->eventId,
            ],
        ]);
    }
}
