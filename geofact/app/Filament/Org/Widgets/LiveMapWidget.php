<?php

namespace App\Filament\Org\Widgets;

use App\Models\Vehicle;
use App\Models\VehicleCurrentPosition;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class LiveMapWidget extends Widget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '30s';

    protected string $view = 'filament.org.widgets.live-map-widget';

    protected function getViewData(): array
    {
        $orgId = Auth::user()?->organization_id;

        $positions = VehicleCurrentPosition::where('organization_id', $orgId)
            ->get()
            ->keyBy('vehicle_id');

        $vehicles = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get()
            ->map(function (Vehicle $vehicle) use ($positions) {
                $pos = $positions->get($vehicle->id);

                return [
                    'id'        => $vehicle->id,
                    'name'      => $vehicle->name,
                    'plate'     => $vehicle->plate,
                    'lat'       => $pos ? (float) $pos->latitude : null,
                    'lng'       => $pos ? (float) $pos->longitude : null,
                    'speed'     => $pos ? (float) $pos->speed_kmh : null,
                    'ts'        => $pos?->position_ts,
                    'freshness' => $pos?->live_freshness_status,
                    'has_pos'   => $pos !== null,
                ];
            })
            ->values();

        return [
            'vehicles'     => $vehicles->toArray(),
            'vehiclesJson' => $vehicles->toJson(),
            'withPos'      => $vehicles->where('has_pos', true)->count(),
            'total'        => $vehicles->count(),
        ];
    }
}
