<?php

namespace App\Kpi\Deferred\Calculators;

use App\Models\Trip;
use Carbon\Carbon;

class FleetUtilizationCalculator
{
    /**
     * Taux d'utilisation = (heures actives / heures période) * 100
     */
    public function compute(string $fleetId, Carbon $from, Carbon $to): array
    {
        $periodHours = $from->diffInHours($to) ?: 1;

        // Durée totale des trips complétés sur la période
        $activeMinutes = Trip::where('fleet_id', $fleetId)
            ->where('status', 'completed')
            ->whereBetween('started_at', [$from, $to])
            ->sum('duration_minutes');

        $activeHours      = $activeMinutes / 60;
        $utilizationRate  = round(($activeHours / $periodHours) * 100, 2);

        return [
            'scope_type'     => 'fleet',
            'scope_id'       => $fleetId,
            'kpi_type'       => 'fleet_utilization_rate',
            'value'          => min(100, $utilizationRate),
            'unit'           => '%',
            'breakdown'      => [
                'active_hours'  => round($activeHours, 2),
                'period_hours'  => $periodHours,
            ],
        ];
    }
}
