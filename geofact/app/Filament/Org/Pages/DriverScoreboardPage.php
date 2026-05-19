<?php

namespace App\Filament\Org\Pages;

use App\Models\Driver;
use App\Models\KpiRecord;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $kpiScores = KpiRecord::where('organization_id', $orgId)
            ->where('kpi_type', 'driver_score')
            ->where('scope_type', 'driver')
            ->where('mode', 'DF')
            ->where('period_from', '>=', $from)
            ->get()
            ->groupBy('scope_id')
            ->map(fn ($records) => $records->sortByDesc('version')->first())
            ->sortByDesc('value')
            ->values();

        if ($kpiScores->isNotEmpty()) {
            $driverIds = $kpiScores->pluck('scope_id');
            $drivers   = Driver::whereIn('id', $driverIds)->where('organization_id', $orgId)->get()->keyBy('id');

            $scoreboard = $kpiScores->map(function ($kpi, $index) use ($drivers) {
                $driver = $drivers->get($kpi->scope_id);
                return [
                    'rank'    => $index + 1,
                    'name'    => $driver ? "{$driver->first_name} {$driver->last_name}" : '—',
                    'license' => $driver?->license_number ?? '—',
                    'score'   => (float) $kpi->value,
                    'period'  => $kpi->period_from?->format('d/m') . ' – ' . $kpi->period_to?->format('d/m/Y'),
                    'source'  => 'kpi',
                ];
            });

            $isFallback = false;
        } else {
            // Fallback : calcule depuis les alertes (avant le premier run KPI DF)
            $alertRows = DB::table('alerts')
                ->where('organization_id', $orgId)
                ->whereNotNull('driver_id')
                ->where('triggered_at', '>=', $from)
                ->selectRaw("driver_id,
                    SUM(CASE WHEN severity = 'CRITICAL' THEN 1 ELSE 0 END) as critical_cnt,
                    SUM(CASE WHEN severity = 'HIGH'     THEN 1 ELSE 0 END) as high_cnt,
                    SUM(CASE WHEN severity = 'MEDIUM'   THEN 1 ELSE 0 END) as medium_cnt,
                    SUM(CASE WHEN severity = 'LOW'      THEN 1 ELSE 0 END) as low_cnt,
                    COUNT(*) as total_alerts")
                ->groupBy('driver_id')
                ->get();

            $driverIds = $alertRows->pluck('driver_id')->merge(
                Driver::where('organization_id', $orgId)->where('status', 'active')->pluck('id')
            )->unique();

            $drivers = Driver::whereIn('id', $driverIds)->where('organization_id', $orgId)->get()->keyBy('id');

            // Conducteurs actifs sans alerte = score 100
            $allDrivers = Driver::where('organization_id', $orgId)
                ->where('status', 'active')
                ->get()
                ->map(function (Driver $d) use ($alertRows) {
                    $row = $alertRows->firstWhere('driver_id', $d->id);
                    $penalty = $row
                        ? ($row->critical_cnt * 10 + $row->high_cnt * 5 + $row->medium_cnt * 2 + $row->low_cnt)
                        : 0;
                    return [
                        'driver_id' => $d->id,
                        'name'      => "{$d->first_name} {$d->last_name}",
                        'license'   => $d->license_number ?? '—',
                        'score'     => max(0, 100 - $penalty),
                    ];
                })
                ->sortByDesc('score')
                ->values();

            $scoreboard = $allDrivers->map(fn ($row, $index) => array_merge($row, [
                'rank'   => $index + 1,
                'period' => $from->format('d/m/Y') . ' → ' . now()->format('d/m/Y'),
                'source' => 'alerts',
            ]));

            $isFallback = true;
        }

        return [
            'scoreboard'  => $scoreboard,
            'period'      => $from->format('d/m/Y') . ' → ' . now()->format('d/m/Y'),
            'isFallback'  => $isFallback,
        ];
    }
}
