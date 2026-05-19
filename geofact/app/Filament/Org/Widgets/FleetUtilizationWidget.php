<?php

namespace App\Filament\Org\Widgets;

use App\Models\KpiRecord;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class FleetUtilizationWidget extends ApexChartWidget
{
    protected static ?string $chartId = 'fleetUtilization';
    protected static ?string $heading = 'Utilisation flotte — 30 jours';
    protected static ?int $sort = 8;
    protected static ?int $contentHeight = 250;
    protected int|string|array $columnSpan = 1;

    protected function getOptions(): array
    {
        $orgId = Auth::user()?->organization_id;

        // Try KPI record first (deferred pipeline)
        $kpi = KpiRecord::where('organization_id', $orgId)
            ->where('kpi_type', 'fleet_utilization')
            ->where('scope_type', 'organization')
            ->where('mode', 'DF')
            ->where('period_from', '>=', Carbon::today()->subDays(30))
            ->orderByDesc('computed_at')
            ->first();

        if ($kpi) {
            $value = min(100, max(0, (float) $kpi->value));
        } else {
            // Fallback: vehicles with ≥1 trip in last 30 days / total active
            $totalActive = Vehicle::where('organization_id', $orgId)
                ->where('status', 'active')
                ->count();

            if ($totalActive === 0) {
                $value = 0;
            } else {
                $activeVehicles = Trip::where('organization_id', $orgId)
                    ->whereIn('status', ['completed', 'anomalous', 'active'])
                    ->where('started_at', '>=', Carbon::today()->subDays(30))
                    ->distinct('vehicle_id')
                    ->count('vehicle_id');

                $value = round($activeVehicles / $totalActive * 100, 1);
            }
        }

        return [
            'chart' => [
                'type'    => 'radialBar',
                'height'  => 250,
                'toolbar' => ['show' => false],
            ],
            'series' => [round($value, 1)],
            'labels' => ['Utilisation'],
            'plotOptions' => [
                'radialBar' => [
                    'hollow'    => ['size' => '60%'],
                    'dataLabels' => [
                        'name'  => ['color' => '#9ca3af', 'fontSize' => '13px'],
                        'value' => [
                            'color'      => '#f97316',
                            'fontSize'   => '28px',
                            'fontWeight' => 700,
                            'formatter'  => 'function (val) { return val + "%" }',
                        ],
                    ],
                    'track' => ['background' => '#374151'],
                ],
            ],
            'colors' => ['#f97316'],
            'fill'   => [
                'type'     => 'gradient',
                'gradient' => [
                    'shade'            => 'dark',
                    'type'             => 'horizontal',
                    'shadeIntensity'   => 0.5,
                    'gradientToColors' => ['#1e3a5f'],
                    'inverseColors'    => false,
                    'opacityFrom'      => 1,
                    'opacityTo'        => 1,
                ],
            ],
        ];
    }
}
