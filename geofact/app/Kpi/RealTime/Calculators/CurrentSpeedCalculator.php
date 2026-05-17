<?php

namespace App\Kpi\RealTime\Calculators;

use App\Core\Canonical\CanonicalEvent;

class CurrentSpeedCalculator
{
    public function compute(CanonicalEvent $event): ?array
    {
        if (! isset($event->payload['speed_kmh']) || $event->vehicleId === null) {
            return null;
        }

        return [
            'organization_id' => $event->organizationId,
            'scope_type'      => 'vehicle',
            'scope_id'        => $event->vehicleId,
            'kpi_type'        => 'current_speed',
            'value'           => (float) $event->payload['speed_kmh'],
            'unit'            => 'km/h',
        ];
    }
}
