<?php

namespace App\Filament\Org\Widgets;

use App\Models\Alert;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $orgId = Auth::user()?->organization_id;

        $activeVehicles = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->count();

        $openAlerts = Alert::where('organization_id', $orgId)
            ->whereNotIn('status', ['resolved', 'expired'])
            ->count();

        $criticalAlerts = Alert::where('organization_id', $orgId)
            ->where('severity', 'CRITICAL')
            ->whereNotIn('status', ['resolved', 'expired'])
            ->count();

        $activeTrips = Trip::where('organization_id', $orgId)
            ->whereIn('status', ['active', 'paused'])
            ->count();

        $activeDrivers = Driver::where('organization_id', $orgId)
            ->where('status', 'active')
            ->count();

        $kmThisWeek = (float) Trip::where('organization_id', $orgId)
            ->whereIn('status', ['completed', 'anomalous'])
            ->where('started_at', '>=', Carbon::today()->subDays(7))
            ->sum('distance_km');

        return [
            Stat::make('Véhicules actifs', $activeVehicles)
                ->description('Flotte opérationnelle')
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),

            Stat::make('Alertes ouvertes', $openAlerts)
                ->description($criticalAlerts > 0 ? "{$criticalAlerts} critiques" : 'Aucune critique')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($criticalAlerts > 0 ? 'danger' : ($openAlerts > 0 ? 'warning' : 'success')),

            Stat::make('Trajets en cours', $activeTrips)
                ->description('Actifs ou en pause')
                ->descriptionIcon('heroicon-m-map')
                ->color('info'),

            Stat::make('Conducteurs actifs', $activeDrivers)
                ->description('Disponibles')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('primary'),

            Stat::make('KM cette semaine', number_format($kmThisWeek, 0, ',', ' ') . ' km')
                ->description('Trajets complétés (7 jours)')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($kmThisWeek > 0 ? 'success' : 'gray'),
        ];
    }
}
