<?php

namespace App\Kpi\Deferred\Calculators;

use App\Models\TelemetryEvent;
use App\Models\Trip;
use Carbon\Carbon;

class WeeklyPerformanceCalculator
{
    /**
     * Agrège les métriques de performance hebdomadaire pour une flotte.
     */
    public function compute(string $fleetId, Carbon $from, Carbon $to): array
    {
        $trips = Trip::where('fleet_id', $fleetId)
            ->where('status', 'completed')
            ->whereBetween('started_at', [$from, $to]);

        $tripCount      = (clone $trips)->count();
        $totalDistanceKm = (clone $trips)->sum('distance_km');
        $totalMinutes   = (clone $trips)->sum('duration_minutes');

        $alerts = TelemetryEvent::where('payload->fleet_id', $fleetId)
            ->whereIn('event_type', [
                'alert.overspeed.detected',
                'alert.harsh.braking',
                'alert.stop.suspicious',
            ])
            ->whereBetween('ts', [$from, $to])
            ->count();

        $performanceScore = $tripCount > 0
            ? max(0, 100 - ($alerts / $tripCount) * 10)
            : 0;

        return [
            'scope_type'    => 'fleet',
            'scope_id'      => $fleetId,
            'kpi_type'      => 'weekly_performance',
            'value'         => round($performanceScore, 2),
            'unit'          => 'score',
            'breakdown'     => [
                'trip_count'       => $tripCount,
                'distance_km'      => round($totalDistanceKm, 2),
                'duration_minutes' => $totalMinutes,
                'alert_count'      => $alerts,
            ],
        ];
    }
}
