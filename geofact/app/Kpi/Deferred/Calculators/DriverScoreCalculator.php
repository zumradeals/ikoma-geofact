<?php

namespace App\Kpi\Deferred\Calculators;

use App\Models\TelemetryEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DriverScoreCalculator
{
    /**
     * Formule C-08 :
     * score = 100 - (overspeed_count*3) - (harsh_braking*2) - (night_activity*1) - (suspicious_stop*4)
     * Score minimum : 0
     */
    public function compute(string $driverId, Carbon $from, Carbon $to): array
    {
        $base = TelemetryEvent::where('payload->driver_id', $driverId)
            ->whereBetween('ts', [$from, $to]);

        $overspeedCount   = (clone $base)->where('event_type', 'alert.overspeed.detected')->count();
        $harshBrakingCount = (clone $base)->where('event_type', 'alert.harsh.braking')->count();
        $suspiciousStopCount = (clone $base)->where('event_type', 'alert.stop.suspicious')->count();

        // Activité nocturne : événements entre 22h et 06h (SQLite: strftime, MySQL: HOUR)
        $driver = DB::connection()->getDriverName();
        $nightExpr = $driver === 'sqlite'
            ? "(CAST(strftime('%H', ts) AS INTEGER) >= 22 OR CAST(strftime('%H', ts) AS INTEGER) < 6)"
            : '(HOUR(ts) >= 22 OR HOUR(ts) < 6)';
        $nightCount = (clone $base)->whereRaw($nightExpr)->count();

        $score = 100
            - ($overspeedCount    * 3)
            - ($harshBrakingCount * 2)
            - ($nightCount        * 1)
            - ($suspiciousStopCount * 4);

        return [
            'scope_type'       => 'driver',
            'scope_id'         => $driverId,
            'kpi_type'         => 'driver_score',
            'value'            => max(0, $score),
            'unit'             => 'score',
            'breakdown'        => [
                'overspeed_count'     => $overspeedCount,
                'harsh_braking_count' => $harshBrakingCount,
                'night_activity'      => $nightCount,
                'suspicious_stop'     => $suspiciousStopCount,
            ],
        ];
    }
}
