<?php

namespace App\Insight\KnowledgeBase;

use App\Models\Alert;
use App\Models\Trip;
use Carbon\Carbon;

/**
 * Construit les tendances historiques sur 7, 30 et 90 jours.
 * Permet à l'IA de comparer la période courante avec des fenêtres
 * plus larges et de détecter des dégradations ou améliorations progressives.
 */
class TrendBuilder
{
    public function build(string $scopeType, string $scopeId, string $organizationId): array
    {
        $windows = [
            '7d'  => [Carbon::now()->subDays(7),  Carbon::now()],
            '30d' => [Carbon::now()->subDays(30), Carbon::now()],
            '90d' => [Carbon::now()->subDays(90), Carbon::now()],
            'prev_7d' => [Carbon::now()->subDays(14), Carbon::now()->subDays(7)],
        ];

        $trends = [];

        foreach ($windows as $label => [$from, $to]) {
            $trips  = $this->tripStats($scopeType, $scopeId, $organizationId, $from, $to);
            $alerts = $this->alertStats($scopeType, $scopeId, $organizationId, $from, $to);

            $trends[$label] = [
                'trips_total'    => $trips['total'],
                'km_total'       => $trips['km'],
                'anomalous_pct'  => $trips['anomalous_pct'],
                'alerts_critical'=> $alerts['critical'],
                'alerts_high'    => $alerts['high'],
                'alerts_total'   => $alerts['total'],
            ];
        }

        // Calcul de la direction de tendance 7j vs 7j précédents
        $current  = $trends['7d'];
        $previous = $trends['prev_7d'];

        $riskCurrent  = $this->riskScore($current);
        $riskPrevious = $this->riskScore($previous);

        $delta = $riskCurrent - $riskPrevious;

        $trends['direction'] = match(true) {
            $delta <= -1.0 => 'improving',
            $delta >= 1.0  => 'degrading',
            default        => 'stable',
        };

        $trends['risk_delta'] = round($delta, 1);

        return $trends;
    }

    private function tripStats(string $type, string $id, string $orgId, Carbon $from, Carbon $to): array
    {
        $q = Trip::where('organization_id', $orgId)
            ->whereBetween('started_at', [$from, $to])
            ->whereIn('status', ['completed', 'anomalous']);

        match($type) {
            'vehicle' => $q->where('vehicle_id', $id),
            'driver'  => $q->where('driver_id', $id),
            'fleet'   => $q->where('fleet_id', $id),
            default   => null,
        };

        $trips = $q->get(['status', 'distance_km']);
        $total = $trips->count();

        return [
            'total'         => $total,
            'km'            => round((float) $trips->sum('distance_km'), 1),
            'anomalous_pct' => $total > 0
                ? round($trips->where('status', 'anomalous')->count() / $total * 100, 1)
                : 0,
        ];
    }

    private function alertStats(string $type, string $id, string $orgId, Carbon $from, Carbon $to): array
    {
        $q = Alert::where('organization_id', $orgId)
            ->whereBetween('triggered_at', [$from, $to]);

        match($type) {
            'vehicle' => $q->where('vehicle_id', $id),
            'driver'  => $q->where('driver_id', $id),
            'fleet'   => $q->where('fleet_id', $id),
            default   => null,
        };

        $alerts = $q->get(['severity']);

        return [
            'total'    => $alerts->count(),
            'critical' => $alerts->where('severity', 'CRITICAL')->count(),
            'high'     => $alerts->where('severity', 'HIGH')->count(),
        ];
    }

    private function riskScore(array $stats): float
    {
        return ($stats['alerts_critical'] * 3.0)
            + ($stats['alerts_high'] * 1.5)
            + ($stats['anomalous_pct'] * 0.1);
    }
}
