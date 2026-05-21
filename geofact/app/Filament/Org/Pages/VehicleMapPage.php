<?php

namespace App\Filament\Org\Pages;

use App\Models\GeoZone;
use App\Models\Vehicle;
use App\Models\VehicleCurrentPosition;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class VehicleMapPage extends Page
{
    protected static ?string $title = 'Carte des vehicules';
    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.org.pages.vehicle-map';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-map-pin';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public function getViewData(): array
    {
        $orgId = Auth::user()?->organization_id;

        $positions = VehicleCurrentPosition::where('organization_id', $orgId)
            ->get()
            ->keyBy('vehicle_id');

        $vehicles = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->with('fleet')
            ->get()
            ->map(function (Vehicle $vehicle) use ($positions) {
                $pos = $positions->get($vehicle->id);

                return [
                    'id'        => $vehicle->id,
                    'name'      => $vehicle->name,
                    'plate'     => $vehicle->plate,
                    'fleet'     => $vehicle->fleet?->name ?? '-',
                    'status'    => $vehicle->status,
                    'lat'       => $pos ? (float) $pos->latitude : null,
                    'lng'       => $pos ? (float) $pos->longitude : null,
                    'speed'     => $pos ? (float) $pos->speed_kmh : null,
                    'heading'   => $pos ? (int) $pos->heading : null,
                    'ts'        => $pos?->position_ts,
                    'freshness' => $pos?->live_freshness_status,
                    'has_pos'   => $pos !== null,
                ];
            })
            ->values();

        $withPos    = $vehicles->where('has_pos', true)->count();
        $withoutPos = $vehicles->where('has_pos', false)->count();

        $geozones = GeoZone::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get(['id', 'name', 'zone_type', 'geometry'])
            ->map(fn ($zone) => [
                'name'     => $zone->name,
                'type'     => $zone->zone_type,
                'geometry' => is_array($zone->geometry) ? $zone->geometry : json_decode($zone->geometry, true),
            ])
            ->values();

        return [
            'vehicles'     => $vehicles,
            'withPos'      => $withPos,
            'withoutPos'   => $withoutPos,
            'vehiclesJson' => $vehicles->where('has_pos', true)->values()->toJson(),
            'geoZonesJson' => $geozones->toJson(),
        ];
    }
}
