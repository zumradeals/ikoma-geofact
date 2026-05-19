<?php

namespace App\Filament\Org\Widgets;

use App\Models\Alert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class AlertsTrendChart extends ApexChartWidget
{
    protected static ?string $chartId = 'alertsTrend';
    protected static ?string $heading = 'Évolution des alertes — 7 derniers jours';
    protected static ?int $sort = 7;
    protected static ?int $contentHeight = 280;
    protected int|string|array $columnSpan = 2;
    protected ?string $pollingInterval = '20s';

    protected function getOptions(): array
    {
        $orgId  = Auth::user()?->organization_id;
        $driver = DB::connection()->getDriverName();

        $days = collect(range(6, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', triggered_at)"
            : "DATE(triggered_at)";

        $rows = Alert::where('organization_id', $orgId)
            ->where('triggered_at', '>=', Carbon::today()->subDays(6))
            ->selectRaw("{$dateExpr} as day, severity, COUNT(*) as total")
            ->groupByRaw("{$dateExpr}, severity")
            ->get()
            ->groupBy('day');

        $severities = ['LOW' => 'Faible', 'MEDIUM' => 'Moyenne', 'HIGH' => 'Haute', 'CRITICAL' => 'Critique'];
        $colors      = ['LOW' => '#6b7280', 'MEDIUM' => '#f59e0b', 'HIGH' => '#ef4444', 'CRITICAL' => '#7f1d1d'];

        $series = [];
        foreach ($severities as $sev => $label) {
            $series[] = [
                'name' => $label,
                'data' => $days->map(fn ($d) => (int) ($rows->get($d->toDateString())?->firstWhere('severity', $sev)?->total ?? 0))->values()->toArray(),
            ];
        }

        return [
            'chart' => [
                'type'    => 'bar',
                'height'  => 280,
                'stacked' => true,
                'toolbar' => ['show' => false],
            ],
            'series' => $series,
            'xaxis'  => [
                'categories' => $days->map(fn ($d) => $d->format('d/m'))->values()->toArray(),
                'labels'     => ['style' => ['colors' => '#9ca3af', 'fontSize' => '12px']],
            ],
            'yaxis' => [
                'labels' => ['style' => ['colors' => '#9ca3af']],
            ],
            'colors' => array_values($colors),
            'legend' => ['position' => 'top', 'labels' => ['colors' => '#9ca3af']],
            'plotOptions' => [
                'bar' => ['borderRadius' => 3, 'columnWidth' => '55%'],
            ],
            'dataLabels' => ['enabled' => false],
            'grid'       => ['borderColor' => '#374151'],
        ];
    }
}
