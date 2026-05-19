<?php

namespace App\Jobs;

use App\Insight\InsightEngine;
use App\Models\Fleet;
use App\Models\Organization;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Génère automatiquement les Insights IA quotidiens (C-06).
 * Scope : fleet + top 5 véhicules actifs par organisation.
 * Planifié à 03h30 dans routes/console.php (après KPI DF à 01h00/01h30).
 */
class GenerateInsightsJob
{
    use Dispatchable;

    public function handle(InsightEngine $engine): void
    {
        Log::info('geofact.insights.daily.started');

        $from = Carbon::yesterday()->startOfDay();
        $to   = Carbon::yesterday()->endOfDay();

        $organizations = Organization::where('status', 'active')->get();

        foreach ($organizations as $org) {
            $this->generateForOrg($engine, $org->id, $from, $to);
        }

        Log::info('geofact.insights.daily.completed', ['orgs' => $organizations->count()]);
    }

    private function generateForOrg(InsightEngine $engine, string $orgId, Carbon $from, Carbon $to): void
    {
        // Insight niveau flotte (scope organization)
        $fleets = Fleet::where('organization_id', $orgId)->where('status', 'active')->get();

        foreach ($fleets as $fleet) {
            try {
                $engine->generate('fleet', $fleet->id, $orgId, $from, $to);
                Log::info('geofact.insights.daily.fleet', ['fleet_id' => $fleet->id]);
            } catch (\Throwable $e) {
                Log::warning('geofact.insights.daily.fleet_failed', [
                    'fleet_id' => $fleet->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // Top 5 véhicules les plus actifs hier (scope vehicle)
        $topVehicleIds = Trip::where('organization_id', $orgId)
            ->whereIn('status', ['completed', 'anomalous'])
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('vehicle_id, COUNT(*) as trip_count')
            ->groupBy('vehicle_id')
            ->orderByDesc('trip_count')
            ->limit(5)
            ->pluck('vehicle_id');

        foreach ($topVehicleIds as $vehicleId) {
            try {
                $engine->generate('vehicle', $vehicleId, $orgId, $from, $to);
                Log::info('geofact.insights.daily.vehicle', ['vehicle_id' => $vehicleId]);
            } catch (\Throwable $e) {
                Log::warning('geofact.insights.daily.vehicle_failed', [
                    'vehicle_id' => $vehicleId, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
