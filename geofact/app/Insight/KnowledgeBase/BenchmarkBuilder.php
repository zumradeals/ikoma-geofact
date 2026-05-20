<?php

namespace App\Insight\KnowledgeBase;

use App\Models\Alert;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;

/**
 * Compare un véhicule ou un conducteur avec ses pairs dans la même organisation.
 * Donne à l'IA le positionnement relatif (quartile, écart à la moyenne)
 * pour contextualiser ses recommandations.
 */
class BenchmarkBuilder
{
    public function build(string $scopeType, string $scopeId, string $organizationId): array
    {
        return match($scopeType) {
            'vehicle' => $this->vehicleBenchmark($scopeId, $organizationId),
            'driver'  => $this->driverBenchmark($scopeId, $organizationId),
            'fleet'   => $this->fleetBenchmark($scopeId, $organizationId),
            default   => ['available' => false],
        };
    }

    private function vehicleBenchmark(string $vehicleId, string $orgId): array
    {
        $from = Carbon::now()->subDays(30);
        $to   = Carbon::now();

        // Récupère tous les véhicules actifs de l'org
        $allVehicleIds = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->pluck('id');

        if ($allVehicleIds->count() < 2) {
            return ['available' => false, 'reason' => 'less_than_2_vehicles'];
        }

        // Calcule les stats de chaque véhicule sur 30 jours
        $stats = [];
        foreach ($allVehicleIds as $vid) {
            $trips  = Trip::where('vehicle_id', $vid)
                ->where('organization_id', $orgId)
                ->whereBetween('started_at', [$from, $to])
                ->whereIn('status', ['completed', 'anomalous'])
                ->get(['distance_km', 'status']);

            $alerts = Alert::where('vehicle_id', $vid)
                ->where('organization_id', $orgId)
                ->whereBetween('triggered_at', [$from, $to])
                ->whereIn('severity', ['CRITICAL', 'HIGH'])
                ->count();

            $stats[$vid] = [
                'km'      => (float) $trips->sum('distance_km'),
                'trips'   => $trips->count(),
                'anomaly' => $trips->where('status', 'anomalous')->count(),
                'alerts'  => $alerts,
            ];
        }

        $target = $stats[$vehicleId] ?? ['km' => 0, 'trips' => 0, 'anomaly' => 0, 'alerts' => 0];

        // Classement KM
        $allKm     = array_column($stats, 'km');
        $avgKm     = count($allKm) > 0 ? array_sum($allKm) / count($allKm) : 0;
        $kmRank    = $this->percentileRank($target['km'], $allKm);
        $position  = $this->quartileLabel($kmRank);

        // Classement alertes (inverse — moins d'alertes = meilleur)
        $allAlerts = array_column($stats, 'alerts');
        $avgAlerts = count($allAlerts) > 0 ? array_sum($allAlerts) / count($allAlerts) : 0;

        return [
            'available'          => true,
            'scope'              => 'last_30_days',
            'peer_count'         => $allVehicleIds->count(),
            'fleet_position'     => $position,
            'km_percentile'      => $kmRank,
            'target_km'          => round($target['km'], 1),
            'fleet_avg_km'       => round($avgKm, 1),
            'target_alerts'      => $target['alerts'],
            'fleet_avg_alerts'   => round($avgAlerts, 1),
            'target_trips'       => $target['trips'],
            'target_anomaly_pct' => $target['trips'] > 0
                ? round($target['anomaly'] / $target['trips'] * 100, 1)
                : 0,
        ];
    }

    private function driverBenchmark(string $driverId, string $orgId): array
    {
        // Structure similaire à vehicleBenchmark mais sur driver_id
        $from = Carbon::now()->subDays(30);

        $trips = Trip::where('driver_id', $driverId)
            ->where('organization_id', $orgId)
            ->whereBetween('started_at', [$from, Carbon::now()])
            ->whereIn('status', ['completed', 'anomalous'])
            ->get(['distance_km', 'status']);

        $alerts = Alert::where('driver_id', $driverId)
            ->where('organization_id', $orgId)
            ->whereBetween('triggered_at', [$from, Carbon::now()])
            ->count();

        return [
            'available'     => true,
            'scope'         => 'last_30_days',
            'driver_km'     => round((float) $trips->sum('distance_km'), 1),
            'driver_trips'  => $trips->count(),
            'driver_alerts' => $alerts,
            'anomaly_pct'   => $trips->count() > 0
                ? round($trips->where('status', 'anomalous')->count() / $trips->count() * 100, 1)
                : 0,
        ];
    }

    private function fleetBenchmark(string $fleetId, string $orgId): array
    {
        $from = Carbon::now()->subDays(30);

        $trips = Trip::where('fleet_id', $fleetId)
            ->where('organization_id', $orgId)
            ->whereBetween('started_at', [$from, Carbon::now()])
            ->whereIn('status', ['completed', 'anomalous'])
            ->get(['distance_km', 'status', 'vehicle_id']);

        $vehicles = Vehicle::where('organization_id', $orgId)
            ->where('fleet_id', $fleetId)
            ->where('status', 'active')
            ->count();

        return [
            'available'      => true,
            'scope'          => 'last_30_days',
            'active_vehicles'=> $vehicles,
            'total_km'       => round((float) $trips->sum('distance_km'), 1),
            'total_trips'    => $trips->count(),
            'active_vehicles_with_trips' => $trips->pluck('vehicle_id')->unique()->count(),
            'utilization_pct' => $vehicles > 0
                ? round($trips->pluck('vehicle_id')->unique()->count() / $vehicles * 100, 1)
                : 0,
            'anomaly_pct'    => $trips->count() > 0
                ? round($trips->where('status', 'anomalous')->count() / $trips->count() * 100, 1)
                : 0,
        ];
    }

    private function percentileRank(float $value, array $all): int
    {
        if (count($all) === 0) return 50;
        $below = count(array_filter($all, fn ($v) => $v < $value));
        return (int) round($below / count($all) * 100);
    }

    private function quartileLabel(int $percentile): string
    {
        return match(true) {
            $percentile >= 75 => 'top_quartile',
            $percentile >= 50 => 'above_average',
            $percentile >= 25 => 'average',
            $percentile >= 10 => 'below_average',
            default           => 'bottom_quartile',
        };
    }
}
