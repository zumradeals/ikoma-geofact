<?php

namespace App\Filament\Org\Widgets;

use App\Models\KpiRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class FleetUtilizationWidget extends ApexChartWidget
{
    protected static ?string $chartId = 'fleetUtilization';
    protected static ?string $heading = 'Utilisation flotte — 30 jours';
    protected static ?int $sort = 7;
    protected static ?int $contentHeight = 250;
    protected int|string|array $columnSpan = 1;

    protected function getOptions(): array
    {
        $orgId = Auth::user()?->organization_id;

        // Dernier KPI fleet_utilization scope organisation
        $kpi = KpiRecord::where('organization_id', $orgId)
            ->where('kpi_type', 'fleet_utilization')
            ->where('scope_type', 'organization')
            ->where('mode', 'DF')
            ->where('period_from', '>=', Carbon::today()->subDays(30))
            ->orderByDesc('computed_at')
            ->first();

        $value = $kpi ? min(100, max(0, (float) $kpi->value)) : 0;

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
                            'color'     => '#f97316',
                            'fontSize'  => '28px',
                            'fontWeight' => 700,
                            'formatter' => 'function (val) { return val + "%" }',
                        ],
                    ],
                    'track' => ['background' => '#374151'],
                ],
            ],
            'colors' => ['#f97316'],
            'fill'   => ['type' => 'gradient', 'gradient' => ['shade' => 'dark', 'type' => 'horizontal', 'shadeIntensity' => 0.5, 'gradientToColors' => ['#1e3a5f'], 'inverseColors' => false, 'opacityFrom' => 1, 'opacityTo' => 1]],
        ];
    }
}
