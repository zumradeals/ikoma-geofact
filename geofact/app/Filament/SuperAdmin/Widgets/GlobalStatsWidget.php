<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Alert;
use App\Models\Connector;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GlobalStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalOrgs       = Organization::where('status', 'active')->count();
        $totalVehicles   = Vehicle::where('status', 'active')->count();
        $totalUsers      = User::where('status', 'active')->count();
        $activeConnectors = Connector::where('status', 'active')->count();
        $openAlerts      = Alert::whereNotIn('status', ['resolved', 'expired'])->count();
        $criticalAlerts  = Alert::where('severity', 'CRITICAL')
            ->whereNotIn('status', ['resolved', 'expired'])->count();
        $activeTrips     = Trip::whereIn('status', ['active', 'paused'])->count();

        return [
            Stat::make('Organisations actives', $totalOrgs)
                ->description('Tous tenants confondus')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Véhicules en flotte', $totalVehicles)
                ->description('Statut actif')
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),

            Stat::make('Utilisateurs actifs', $totalUsers)
                ->description('Tous rôles')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Connecteurs actifs', $activeConnectors)
                ->description('GPS / IoT')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('success'),

            Stat::make('Alertes ouvertes', $openAlerts)
                ->description($criticalAlerts > 0 ? "{$criticalAlerts} critiques" : 'Aucune critique')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($criticalAlerts > 0 ? 'danger' : ($openAlerts > 0 ? 'warning' : 'success')),

            Stat::make('Trajets en cours', $activeTrips)
                ->description('Actifs ou en pause')
                ->descriptionIcon('heroicon-m-map')
                ->color('warning'),
        ];
    }
}
