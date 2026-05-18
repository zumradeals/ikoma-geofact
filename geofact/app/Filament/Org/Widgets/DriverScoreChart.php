<?php

namespace App\Filament\Org\Widgets;

use App\Models\Driver;
use App\Models\KpiRecord;
use Illuminate\Support\Facades\Auth;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class DriverScoreChart extends ApexChartWidget
{
    protected static ?string $chartId = 'driverScore';
    protected static ?string $heading = 'Score conducteurs (top 5)';
    protected static ?int $sort = 4;
    protected static ?int $contentHeight = 280;
    protected int|string|array $columnSpan = 1;

    protected function getOptions(): array
    {
        $orgId = Auth::user()?->organization_id;

        // Récupère la dernière version du driver_score par conducteur
        $scores = KpiRecord::where('organization_id', $orgId)
            ->where('kpi_type', 'driver_score')
            ->where('scope_type', 'driver')
            ->orderBy('version', 'desc')
            ->get()
            ->unique('scope_id')
            ->take(5);

        $driverIds = $scores->pluck('scope_id');
        $drivers   = Driver::whereIn('id', $driverIds)
            ->get()
            ->keyBy('id');

        $labels = $scores->map(fn ($r) => isset($drivers[$r->scope_id])
            ? $drivers[$r->scope_id]->first_name . ' ' . $drivers[$r->scope_id]->last_name
            : substr($r->scope_id, 0, 8)
        )->values()->toArray();

        $data = $scores->map(fn ($r) => (int) $r->value)->values()->toArray();

        return [
            'chart' => [
                'type'    => 'bar',
                'height'  => 280,
                'toolbar' => ['show' => false],
            ],
            'series' => [
                ['name' => 'Score', 'data' => $data],
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
