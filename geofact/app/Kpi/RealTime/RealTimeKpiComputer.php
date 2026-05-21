<?php

namespace App\Kpi\RealTime;

use App\Models\KpiRecord;
use App\Models\TelemetryEvent;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Calcule les KPIs agrégés journaliers en temps réel pour un véhicule.
 *
 * Appelé à chaque telemetry.position.updated pour que le paquet IA
 * dispose de données fraîches pendant la journée (pas uniquement la nuit).
 *
 * Contrat DC-12 : INSERT uniquement sur kpi_records — jamais UPDATE.
 */
class RealTimeKpiComputer
{
    /**
     * Insère un snapshot KPI du jour pour le véhicule donné.
     *
     * Valeurs calculées :
     *   - distance_km_today  : somme des trajets complétés du jour (km)
     *   - speed_avg_today    : vitesse moyenne des points > 0 km/h du jour
     *   - active_time_today  : durée totale des trajets complétés du jour (minutes)
     */
    public function computeForVehicle(string $vehicleId, string $orgId): void
    {
        // Throttle : un snapshot toutes les 15 minutes par véhicule
        $cacheKey = 'kpi_rt_computed_' . $vehicleId;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addMinutes(15));

        $todayStart = Carbon::now()->startOfDay();
        $now        = Carbon::now();

        // ── distance_km_today et active_time_today ─────────────────────────
        // Lus depuis les trips complétés du jour (plus fiables que le brut GPS)
        $trips = Trip::where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->where('status', 'completed')
            ->whereBetween('started_at', [$todayStart, $now])
            ->get(['distance_km', 'duration_minutes']);

        $distanceKm    = round((float) $trips->sum('distance_km'), 2);
        $activeMinutes = round((float) $trips->sum('duration_minutes'), 0);

        // ── speed_avg_today ────────────────────────────────────────────────
        // Moyenne des points GPS avec vitesse > 0 dans telemetry_events du jour
        $speedAvg = (float) TelemetryEvent::where('vehicle_id', $vehicleId)
            ->where('organization_id', $orgId)
            ->whereBetween('ts', [$todayStart, $now])
            ->whereNotNull('speed_kmh')
            ->where('speed_kmh', '>', 0)
            ->avg('speed_kmh');

        $speedAvg = round($speedAvg, 1);

        $computedAt = $now;

        $kpis = [
            ['kpi_type' => 'distance_km_today',   'value' => $distanceKm,    'unit' => 'km'],
            ['kpi_type' => 'speed_avg_today',      'value' => $speedAvg,      'unit' => 'km/h'],
            ['kpi_type' => 'active_time_today',    'value' => $activeMinutes, 'unit' => 'minutes'],
        ];

        foreach ($kpis as $kpi) {
            KpiRecord::create([
                'id'              => Str::uuid()->toString(),
                'organization_id' => $orgId,
                'scope_type'      => 'vehicle',
                'scope_id'        => $vehicleId,
                'kpi_type'        => $kpi['kpi_type'],
                'mode'            => 'RT',
                'period_from'     => $todayStart,
                'period_to'       => $computedAt,
                'value'           => $kpi['value'],
                'unit'            => $kpi['unit'],
                'version'         => 1,
                'computed_at'     => $computedAt,
            ]);
        }

        Log::info('geofact.kpi.rt.daily_snapshot_inserted', [
            'vehicle_id'     => $vehicleId,
            'distance_km'    => $distanceKm,
            'speed_avg'      => $speedAvg,
            'active_minutes' => $activeMinutes,
        ]);
    }
}
