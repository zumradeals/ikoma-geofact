<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Organization;
use App\Models\Vehicle;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class OrgsActivityChart extends ApexChartWidget
{
    protected static ?string $chartId = 'orgsActivity';
    protected static ?string $heading = 'Véhicules par organisation (top 8)';
    protected static ?int $sort = 2;
    protected static ?int $contentHeight = 300;
    protected int|string|array $columnSpan = 'full';

    protected function getOptions(): array
    {
        $data = Organization::where('status', 'active')
            ->withCount(['vehicles' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('vehicles_count')
            ->limit(8)
            ->get();

        return [
            'chart' => [
                'type'    => 'bar',
                'height'  => 300,
                'toolbar' => ['show' => false],
            ],
            'series' => [
                ['name' => 'Véhicules actifs', 'data' => $data->pluck('vehicles_count')->toArray()],
            ],
            'xaxis' => [
                'categories' => $data->pluck('name')->toArray(),
                'labels'     => ['style' => ['colors' => '#9ca3af', 'fontWeight' => 600]],
            ],
            'yaxis' => [
                'min'    => 0,
                'labels' => ['style' => ['colors' => '#9ca3af', 'fontWeight' => 600]],
            ],
            'colors'      => ['#1e3a5f'],
            'plotOptions' => ['bar' => ['borderRadius' => 4, 'horizontal' => true]],
            'dataLabels'  => ['enabled' => true, 'style' => ['colors' => ['#fff']]],
            'grid'        => ['borderColor' => '#374151'],
            'tooltip'     => ['theme' => 'dark'],
        ];
    }
}
