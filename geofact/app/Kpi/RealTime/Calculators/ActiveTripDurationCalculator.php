<?php

namespace App\Kpi\RealTime\Calculators;

use App\Core\Canonical\CanonicalEvent;
use App\Models\Trip;

class ActiveTripDurationCalculator
{
    public function compute(CanonicalEvent $event): ?array
    {
        if ($event->vehicleId === null) {
            return null;
        }

        $activeTrip = Trip::where('vehicle_id', $event->vehicleId)
            ->whereIn('status', ['active', 'paused'])
            ->first();

        if (! $activeTrip || ! $activeTrip->started_at) {
            return null;
        }

        $durationMinutes = $activeTrip->started_at->diffInMinutes(now());

        return [
            'organization_id' => $event->organizationId,
            'scope_type'      => 'vehicle',
            'scope_id'        => $event->vehicleId,
            'kpi_type'        => 'active_trip_duration',
            'value'           => $durationMinutes,
            'unit'            => 'minutes',
        ];
    }
}
