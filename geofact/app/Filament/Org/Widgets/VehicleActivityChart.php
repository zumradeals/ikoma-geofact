<?php

namespace App\Filament\Org\Widgets;

use App\Models\Trip;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class VehicleActivityChart extends ApexChartWidget
{
    protected static ?string $chartId = 'vehicleActivity';
    protected static ?string $heading = 'Activité véhicules — 7 derniers jours';
    protected static ?int $sort = 3;
    protected static ?int $contentHeight = 280;
    protected int|string|array $columnSpan = 2;

    protected function getOptions(): array
    {
        $orgId = Auth::user()?->organization_id;
        $days  = collect(CarbonPeriod::create(now()->subDays(6)->startOfDay(), now()->endOfDay()))
            ->map(fn (Carbon $d) => $d->format('Y-m-d'));

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();

        $trips = Trip::where('organization_id', $orgId)
            ->where('started_at', '>=', now()->subDays(6)->startOfDay())
            ->when(
                $driver === 'sqlite',
                fn ($q) => $q->selectRaw("strftime('%Y-%m-%d', started_at) as day, COUNT(*) as total"),
                fn ($q) => $q->selectRaw("DATE(started_at) as day, COUNT(*) as total")
            )
            ->groupBy('day')
            ->pluck('total', 'day');

        $categories = $days->map(fn ($d) => Carbon::parse($d)->format('d/m'))->values()->toArray();
        $data        = $days->map(fn ($d) => (int) ($trips[$d] ?? 0))->values()->toArray();

        return [
            'chart' => [
                'type'    => 'area',
                'height'  => 280,
                'toolbar' => ['show' => false],
                'zoom'    => ['enabled' => false],
            ],
            'series' => [
                ['name' => 'Trajets', 'data' => $data],
            ],
            'xaxis' => [
                'categories' => $categories,
                'labels'     => ['style' => ['colors' => '#9ca3af', 'fontWeight' => 600]],
            ],
            'yaxis' => [
                'min'    => 0,
                'labels' => ['style' => ['colors' => '#9ca3af', 'fontWeight' => 600]],
            ],
            'colors'     => ['#1e3a5f'],
            'fill'       => ['type' => 'gradient', 'gradient' => ['opacityFrom' => 0.4, 'opacityTo' => 0.05]],
            'stroke'     => ['curve' => 'smooth', 'width' => 2],
            'dataLabels' => ['enabled' => false],
            'grid'       => ['borderColor' => '#374151'],
            'tooltip'    => ['theme' => 'dark'],
        ];
    }
}
