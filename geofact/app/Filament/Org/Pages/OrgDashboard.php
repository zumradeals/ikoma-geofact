<?php

namespace App\Filament\Org\Pages;

use App\Filament\Org\Widgets\AlertsBySeverityChart;
use App\Filament\Org\Widgets\AlertsTrendChart;
use App\Filament\Org\Widgets\CriticalAlertsWidget;
use App\Filament\Org\Widgets\DriverScoreChart;
use App\Filament\Org\Widgets\FleetUtilizationWidget;
use App\Filament\Org\Widgets\LiveMapWidget;
use App\Filament\Org\Widgets\StatsOverviewWidget;
use App\Filament\Org\Widgets\VehicleActivityChart;
use Filament\Pages\Dashboard;

class OrgDashboard extends Dashboard
{
    protected static ?string $title = 'Tableau de bord';
    protected static ?int $navigationSort = -1;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-home';
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,   // sort 1 — full width (5 KPI cards)
            LiveMapWidget::class,          // sort 2 — full width (live vehicle map)
            VehicleActivityChart::class,   // sort 3 — 2 cols (7-day trip chart)
            AlertsBySeverityChart::class,  // sort 4 — 1 col (donut by severity)
            DriverScoreChart::class,       // sort 5 — 1 col (driver scores)
            CriticalAlertsWidget::class,   // sort 6 — full width (only when critical)
            AlertsTrendChart::class,       // sort 7 — 2 cols (7-day stacked bar)
            FleetUtilizationWidget::class, // sort 8 — 1 col (radial utilization)
        ];
    }
}
