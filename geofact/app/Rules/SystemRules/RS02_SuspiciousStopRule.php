<?php

namespace App\Rules\SystemRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Models\TelemetryEvent;
use App\Rules\Contracts\RuleInterface;
use App\Rules\Engine\RuleConfigResolver;
use Illuminate\Support\Str;

class RS02_SuspiciousStopRule implements RuleInterface
{
    public function __construct(private RuleConfigResolver $resolver) {}

    public function getRuleId(): string  { return 'RS02'; }
    public function getRuleType(): string { return 'RS'; }

    public function applies(CanonicalEvent $event): bool
    {
        return $event->vehicleId !== null
            && isset($event->payload['speed_kmh'])
            && ((float) $event->payload['speed_kmh']) <= 0
            && (($event->payload['ignition'] ?? null) == false || ($event->payload['ignition'] ?? null) === null)
            && $this->resolver->isEnabled('RS02', $event->organizationId);
    }

    public function evaluate(CanonicalEvent $event): ?Alert
    {
        $config         = $this->resolver->resolve('RS02', $event->organizationId);
        $thresholdHours = (int) ($config['threshold_hours'] ?? 4);

        $lastMoving = TelemetryEvent::where('vehicle_id', $event->vehicleId)
            ->where('organization_id', $event->organizationId)
            ->where('speed_kmh', '>', 0)
            ->where('ts', '<=', $event->timestamp)
            ->orderByDesc('ts')
            ->first();

        if (! $lastMoving) {
            return null;
        }

        $elapsedHours = $lastMoving->ts->diffInHours($event->timestamp);

        if ($elapsedHours < $thresholdHours) {
            return null;
        }

        return new Alert([
            'id'              => Str::uuid()->toString(),
            'organization_id' => $event->organizationId,
            'vehicle_id'      => $event->vehicleId,
            'trip_id'         => $event->tripId,
            'rule_id'         => $this->getRuleId(),
            'rule_type'       => $this->getRuleType(),
            'event_type'      => 'alert.stop.suspicious',
            'severity'        => 'MEDIUM',
            'status'          => 'open',
            'triggered_at'    => $event->timestamp,
            'payload'         => [
                'stopped_since_hours' => $elapsedHours,
                'threshold_hours'     => $thresholdHours,
                'last_stop_ts'        => $lastMoving->ts->toIso8601String(),
                'canonical_id'        => $event->eventId,
            ],
        ]);
    }
}
