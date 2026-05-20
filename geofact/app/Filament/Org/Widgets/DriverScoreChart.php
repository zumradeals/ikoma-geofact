<?php

namespace App\Filament\Org\Widgets;

use App\Models\Alert;
use App\Models\Driver;
use App\Models\KpiRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class DriverScoreChart extends ApexChartWidget
{
    protected static ?string $chartId = 'driverScore';
    protected static ?string $heading = 'Score conducteurs (top 5)';
    protected static ?int $sort = 5;
    protected static ?int $contentHeight = 280;
    protected int|string|array $columnSpan = 1;

    protected function getOptions(): array
    {
        $orgId = Auth::user()?->organization_id;

        // Try KPI records first (computed by deferred pipeline)
        $kpiScores = KpiRecord::where('organization_id', $orgId)
            ->where('kpi_type', 'driver_score')
            ->where('scope_type', 'driver')
            ->orderBy('version', 'desc')
            ->get()
            ->unique('scope_id')
            ->take(5);

        if ($kpiScores->isNotEmpty()) {
            $driverIds = $kpiScores->pluck('scope_id');
            $drivers   = Driver::whereIn('id', $driverIds)->get()->keyBy('id');
            $labels    = $kpiScores->map(fn ($r) => isset($drivers[$r->scope_id])
                ? $drivers[$r->scope_id]->first_name . ' ' . $drivers[$r->scope_id]->last_name
                : substr($r->scope_id, 0, 8)
            )->values()->toArray();
            $data = $kpiScores->map(fn ($r) => (int) $r->value)->values()->toArray();
        } else {
            // Fallback: compute from alerts in last 30 days grouped by driver_id
            $alertRows = DB::table('alerts')
                ->where('organization_id', $orgId)
                ->whereNotNull('driver_id')
                ->where('triggered_at', '>=', Carbon::today()->subDays(30))
                ->selectRaw("driver_id,
                    SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) as critical_cnt,
                    SUM(CASE WHEN severity = 'HIGH'     THEN 1 ELSE 0 END) as high_cnt,
                    SUM(CASE WHEN severity = 'MEDIUM'   THEN 1 ELSE 0 END) as medium_cnt")
                ->groupBy('driver_id')
                ->orderByRaw("(SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) * 10
                             + SUM(CASE WHEN severity = 'HIGH'     THEN 1 ELSE 0 END) * 5
                             + SUM(CASE WHEN severity = 'MEDIUM'   THEN 1 ELSE 0 END) * 2) ASC")
                ->limit(5)
                ->get();

            if ($alertRows->isNotEmpty()) {
                $driverIds = $alertRows->pluck('driver_id');
                $drivers   = Driver::whereIn('id', $driverIds)->get()->keyBy('id');
                $labels    = $alertRows->map(fn ($r) => isset($drivers[$r->driver_id])
                    ? $drivers[$r->driver_id]->first_name . ' ' . $drivers[$r->driver_id]->last_name
                    : substr($r->driver_id, 0, 8)
                )->values()->toArray();
                $data = $alertRows->map(fn ($r) => max(0, 100 - ($r->critical_cnt * 10 + $r->high_cnt * 5 + $r->medium_cnt * 2)))
                    ->values()->toArray();
            } else {
                $labels = ['—'];
                $data   = [0];
            }
        }

        return [
            'chart' => [
                'type'    => 'bar',
                'height'  => 280,
                'toolbar' => ['show' => false],
            ],
            'series' => [
                ['name' => 'Score /100', 'data' => $data],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels'     => ['style' => ['colors' => '#9ca3af', 'fontWeight' => 600]],
            ],
            'yaxis' => [
                'min'    => 0,
                'max'    => 100,
                'labels' => ['style' => ['colors' => '#9ca3af', 'fontWeight' => 600]],
            ],
            'colors'     => ['#f97316'],
            'plotOptions' => [
                'bar' => ['borderRadius' => 4, 'horizontal' => false],
            ],
            'dataLabels' => ['enabled' => true, 'style' => ['colors' => ['#fff']]],
            'grid'       => ['borderColor' => '#374151'],
            'tooltip'    => ['theme' => 'dark'],
        ];
    }
}
