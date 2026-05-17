<?php

namespace App\Kpi\Deferred\Calculators;

use App\Models\TelemetryEvent;
use Carbon\Carbon;

class BehavioralAnalysisCalculator
{
    /**
     * Analyse comportementale des conducteurs sur la période.
     * Produit un score de risque comportemental agrégé pour l'organisation.
     */
    public function compute(string $organizationId, Carbon $from, Carbon $to): array
    {
        $base = TelemetryEvent::where('organization_id', $organizationId)
            ->whereBetween('ts', [$from, $to]);

        $totalEvents   = (clone $base)->count() ?: 1;
        $overspeed     = (clone $base)->where('event_type', 'alert.overspeed.detected')->count();
        $harshBraking  = (clone $base)->where('event_type', 'alert.harsh.braking')->count();
        $nightActivity = (clone $base)
            ->whereRaw('HOUR(ts) >= 22 OR HOUR(ts) < 6')
            ->whereIn('event_type', ['trip.started', 'vehicle.moving'])
            ->count();

        $riskScore = round(
            (($overspeed * 3) + ($harshBraking * 2) + ($nightActivity * 1)) / $totalEvents * 100,
            2
        );

        return [
            'scope_type'   => 'organization',
            'scope_id'     => $organizationId,
            'kpi_type'     => 'behavioral_risk_score',
            'value'        => min(100, $riskScore),
            'unit'         => 'score',
            'breakdown'    => [
                'total_events'  => $totalEvents,
                'overspeed'     => $overspeed,
                'harsh_braking' => $harshBraking,
                'night_activity'=> $nightActivity,
            ],
        ];
    }
}
