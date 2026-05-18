<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Filament\SuperAdmin\Widgets\GlobalStatsWidget;
use App\Filament\SuperAdmin\Widgets\OrgsActivityChart;
use Filament\Pages\Dashboard;

class SuperAdminDashboard extends Dashboard
{
    protected static ?string $title = 'Vue globale';
    protected static ?int $navigationSort = -1;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar-square';
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            GlobalStatsWidget::class,
            OrgsActivityChart::class,
        ];
    }
}
