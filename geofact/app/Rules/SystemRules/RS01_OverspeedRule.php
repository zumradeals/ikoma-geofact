<?php

namespace App\Rules\SystemRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Rules\Contracts\RuleInterface;
use App\Rules\Engine\RuleConfigResolver;
use Illuminate\Support\Str;

class RS01_OverspeedRule implements RuleInterface
{
    public function __construct(private RuleConfigResolver $resolver) {}

    public function getRuleId(): string  { return 'RS01'; }
    public function getRuleType(): string { return 'RS'; }

    public function applies(CanonicalEvent $event): bool
    {
        return isset($event->payload['speed_kmh'])
            && $this->resolver->isEnabled('RS01', $event->organizationId);
    }

    public function evaluate(CanonicalEvent $event): ?Alert
    {
        $config       = $this->resolver->resolve('RS01', $event->organizationId);
        $threshold    = (int) ($config['threshold_kmh'] ?? 90);
        $mediumPct    = (int) ($config['severity_medium_pct'] ?? 20);
        $highPct      = (int) ($config['severity_high_pct'] ?? 40);

        $speed = (float) ($event->payload['speed_kmh'] ?? 0);

        if ($speed <= $threshold) {
            return null;
        }

        $excess   = $speed - $threshold;
        $pct      = ($excess / $threshold) * 100;
        $severity = match(true) {
            $pct < $mediumPct => 'MEDIUM',
            $pct < $highPct   => 'HIGH',
            default           => 'CRITICAL',
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
                'threshold_kmh'  => $threshold,
                'excess_pct'     => round($pct, 2),
                'canonical_id'   => $event->eventId,
            ],
        ]);
    }
}
