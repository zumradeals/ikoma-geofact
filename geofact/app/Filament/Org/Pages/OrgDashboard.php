<?php

namespace App\Filament\Org\Pages;

use App\Filament\Org\Widgets\AlertsBySeverityChart;
use App\Filament\Org\Widgets\DriverScoreChart;
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
            StatsOverviewWidget::class,
            VehicleActivityChart::class,
            AlertsBySeverityChart::class,
            DriverScoreChart::class,
        ];
    }
}
