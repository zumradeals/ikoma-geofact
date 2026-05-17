<?php

namespace App\Kpi\Deferred\Calculators;

use App\Models\TelemetryEvent;
use App\Models\Trip;
use Carbon\Carbon;

class MonthlyReportCalculator
{
    /**
     * Rapport mensuel agrégé pour une organisation.
     */
    public function compute(string $organizationId, Carbon $from, Carbon $to): array
    {
        $trips = Trip::where('organization_id', $organizationId)
            ->whereBetween('started_at', [$from, $to]);

        $completedTrips  = (clone $trips)->where('status', 'completed')->count();
        $totalDistanceKm = (clone $trips)->where('status', 'completed')->sum('distance_km');
        $totalMinutes    = (clone $trips)->where('status', 'completed')->sum('duration_minutes');

        $alerts = TelemetryEvent::where('organization_id', $organizationId)
            ->whereBetween('ts', [$from, $to])
            ->whereIn('event_type', [
                'alert.overspeed.detected',
                'alert.harsh.braking',
                'alert.stop.suspicious',
                'geozone.violated',
            ])
            ->count();

        // Score mensuel global : 100 - pénalités proportionnelles
        $monthlyScore = $completedTrips > 0
            ? max(0, 100 - ($alerts / $completedTrips) * 5)
            : 0;

        return [
            'scope_type'   => 'organization',
            'scope_id'     => $organizationId,
            'kpi_type'     => 'monthly_report_score',
            'value'        => round($monthlyScore, 2),
            'unit'         => 'score',
            'breakdown'    => [
                'completed_trips'  => $completedTrips,
                'distance_km'      => round($totalDistanceKm, 2),
                'duration_hours'   => round($totalMinutes / 60, 2),
                'alert_count'      => $alerts,
            ],
        ];
    }
}
