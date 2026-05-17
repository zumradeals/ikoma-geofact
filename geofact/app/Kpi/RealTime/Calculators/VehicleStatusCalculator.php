<?php

namespace App\Kpi\RealTime\Calculators;

use App\Core\Canonical\CanonicalEvent;

class VehicleStatusCalculator
{
    private const STATUS_MAP = [
        'trip.started'    => 'moving',
        'vehicle.moving'  => 'moving',
        'vehicle.stopped' => 'stopped',
        'vehicle.idle'    => 'idle',
        'trip.ended'      => 'stopped',
        'trip.paused'     => 'idle',
    ];

    public function compute(CanonicalEvent $event): ?array
    {
        $status = self::STATUS_MAP[$event->eventType] ?? null;

        if ($status === null || $event->vehicleId === null) {
            return null;
        }

        return [
            'organization_id' => $event->organizationId,
            'scope_type'      => 'vehicle',
            'scope_id'        => $event->vehicleId,
            'kpi_type'        => 'vehicle_status',
            'value'           => 0,     // valeur numérique symbolique
            'unit'            => $status,
        ];
    }
}
