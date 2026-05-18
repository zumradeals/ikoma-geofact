<?php

namespace App\Filament\Org\Pages;

use App\Models\Driver;
use App\Models\KpiRecord;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DriverScoreboardPage extends Page
{
    protected static ?string $title = 'Classement conducteurs';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.org.pages.driver-scoreboard';

    public static function getNavigationIcon(): string { return 'heroicon-o-trophy'; }
    public static function getNavigationGroup(): ?string { return 'Analytique'; }

    public function getViewData(): array
    {
        $orgId = Auth::user()?->organization_id;
        $from  = Carbon::today()->subDays(30);

        // Dernier score DF par conducteur (version max)
        $scores = KpiRecord::where('organization_id', $orgId)
            ->where('kpi_type', 'driver_score')
            ->where('scope_type', 'driver')
            ->where('mode', 'DF')
            ->where('period_from', '>=', $from)
            ->get()
            ->groupBy('scope_id')
            ->map(fn ($records) => $records->sortByDesc('version')->first())
            ->sortByDesc('value')
            ->values();

        // Joindre les données conducteur
        $driverIds = $scores->pluck('scope_id');
        $drivers   = Driver::whereIn('id', $driverIds)
            ->where('organization_id', $orgId)
            ->get()
            ->keyBy('id');

        $scoreboard = $scores->map(function ($kpi, $index) use ($drivers) {
            $driver = $drivers->get($kpi->scope_id);
            return [
                'rank'       => $index + 1,
                'driver_id'  => $kpi->scope_id,
                'name'       => $driver ? "{$driver->first_name} {$driver->last_name}" : '—',
                'license'    => $driver?->license_number ?? '—',
                'score'      => (float) $kpi->value,
                'period'     => $kpi->period_from?->format('d/m') . ' – ' . $kpi->period_to?->format('d/m/Y'),
                'version'    => $kpi->version,
            ];
        });

        return [
            'scoreboard' => $scoreboard,
            'period'     => $from->format('d/m/Y') . ' → ' . now()->format('d/m/Y'),
        ];
    }
}
