<?php

namespace App\Filament\Org\Pages;

use App\Models\TelemetryEvent;
use App\Models\Vehicle;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VehicleMapPage extends Page
{
    protected static ?string $title = 'Carte des véhicules';
    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.org.pages.vehicle-map';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-map-pin';
    }

    public static function getNavigationGroup(): ?string
    {
        return null; // Navigation racine, avant les groupes
    }

    public function getViewData(): array
    {
        $orgId = Auth::user()?->organization_id;
        $driver = DB::connection()->getDriverName();

        // Dernière position GPS par véhicule (latitude/longitude non nulles)
        $latestEventIds = TelemetryEvent::where('organization_id', $orgId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when(
                $driver === 'sqlite',
                fn ($q) => $q->selectRaw('vehicle_id, MAX(ts) as max_ts')
                            ->groupBy('vehicle_id'),
                fn ($q) => $q->selectRaw('vehicle_id, MAX(ts) as max_ts')
                            ->groupBy('vehicle_id')
            );

        // Sous-requête pour récupérer l'event complet le plus récent par véhicule
        $positions = TelemetryEvent::where('organization_id', $orgId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereIn(
                DB::raw('CONCAT(vehicle_id, "|", ts)'),
                $latestEventIds->get()->map(fn ($r) => $r->vehicle_id . '|' . $r->max_ts)
            )
            ->get()
            ->keyBy('vehicle_id');

        // Véhicules actifs de l'org avec leur dernière position
        $vehicles = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->with('fleet')
            ->get()
            ->map(function (Vehicle $v) use ($positions) {
                $pos = $positions->get($v->id);
                return [
                    'id'        => $v->id,
                    'name'      => $v->name,
                    'plate'     => $v->plate,
                    'fleet'     => $v->fleet?->name ?? '—',
                    'status'    => $v->status,
                    'lat'       => $pos ? (float) $pos->latitude  : null,
                    'lng'       => $pos ? (float) $pos->longitude : null,
                    'speed'     => $pos ? (float) $pos->speed_kmh : null,
                    'heading'   => $pos ? (int)   $pos->heading   : null,
                    'ts'        => $pos ? $pos->ts                 : null,
                    'has_pos'   => $pos !== null,
                ];
            })
            ->values();

        $withPos    = $vehicles->where('has_pos', true)->count();
        $withoutPos = $vehicles->where('has_pos', false)->count();

        return [
            'vehicles'    => $vehicles,
            'withPos'     => $withPos,
            'withoutPos'  => $withoutPos,
            'vehiclesJson' => $vehicles->where('has_pos', true)->values()->toJson(),
        ];
    }
}
