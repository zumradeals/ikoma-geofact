<?php

namespace App\Filament\Org\Widgets;

use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class AlertsBySeverityChart extends ApexChartWidget
{
    protected static ?string $chartId = 'alertsBySeverity';
    protected static ?string $heading = 'Alertes par sévérité';
    protected static ?int $sort = 3;
    protected static ?int $contentHeight = 280;
    protected int|string|array $columnSpan = 1;

    protected function getOptions(): array
    {
        $orgId = Auth::user()?->organization_id;

        $counts = Alert::where('organization_id', $orgId)
            ->whereNotIn('status', ['resolved', 'expired'])
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $labels   = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
        $series   = array_map(fn ($l) => (int) ($counts[$l] ?? 0), $labels);
        $labelsLoc = ['Faible', 'Moyenne', 'Haute', 'Critique'];

        return [
            'chart' => [
                'type'   => 'donut',
                'height' => 280,
            ],
            'series' => $series,
            'labels' => $labelsLoc,
            'colors' => ['#6b7280', '#f59e0b', '#ef4444', '#7f1d1d'],
            'legend' => [
                'position' => 'bottom',
                'labels'   => ['colors' => '#9ca3af'],
            ],
            'dataLabels' => ['enabled' => true],
            'plotOptions' => [
                'pie' => [
                    'donut' => ['size' => '65%'],
                ],
            ],
        ];
    }
}
