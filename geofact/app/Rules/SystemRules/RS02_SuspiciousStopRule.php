<?php

namespace App\Rules\SystemRules;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Alert;
use App\Models\TelemetryEvent;
use App\Rules\Contracts\RuleInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RS02_SuspiciousStopRule implements RuleInterface
{
    // Seuil d'immobilisation suspect — défaut 4h (C-04.2)
    private int $thresholdHours;

    public function __construct(int $thresholdHours = 4)
    {
        $this->thresholdHours = $thresholdHours;
    }

    public function getRuleId(): string { return 'RS02'; }
    public function getRuleType(): string { return 'RS'; }

    public function applies(CanonicalEvent $event): bool
    {
        // S'applique sur tout event de position — détecte l'arrêt prolongé via speed+ignition
        return $event->vehicleId !== null
            && isset($event->payload['speed_kmh'])
            && ((float) $event->payload['speed_kmh']) <= 0
            && (($event->payload['ignition'] ?? null) == false || ($event->payload['ignition'] ?? null) === null);
    }

    public function evaluate(CanonicalEvent $event): ?Alert
    {
        // Cherche le dernier event avec speed > 0 pour calculer depuis quand le véhicule est arrêté
        $lastMoving = TelemetryEvent::where('vehicle_id', $event->vehicleId)
            ->where('speed_kmh', '>', 0)
            ->orderByDesc('ts')
            ->first();

        if (! $lastMoving) {
            return null;
        }

        $stopSince    = $lastMoving->ts;
        $elapsedHours = $stopSince->diffInHours(now());

        if ($elapsedHours < $this->thresholdHours) {
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
                'threshold_hours'     => $this->thresholdHours,
                'last_stop_ts'        => $stopSince->toIso8601String(),
                'canonical_id'        => $event->eventId,
            ],
        ]);
    }
}
