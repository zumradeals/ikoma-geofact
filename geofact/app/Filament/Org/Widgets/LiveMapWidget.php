<?php

namespace App\Filament\Org\Widgets;

use App\Models\TelemetryEvent;
use App\Models\Vehicle;
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

        $latestByVehicle = TelemetryEvent::where('organization_id', $orgId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('vehicle_id, MAX(ts) as max_ts')
            ->groupBy('vehicle_id')
            ->get()
            ->keyBy('vehicle_id');

        $positions = collect();
        if ($latestByVehicle->isNotEmpty()) {
            $vehicleTsMap = $latestByVehicle->map(fn ($r) => $r->max_ts);

            $positions = TelemetryEvent::where('organization_id', $orgId)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where(function ($q) use ($vehicleTsMap) {
                    foreach ($vehicleTsMap as $vehicleId => $maxTs) {
                        $q->orWhere(fn ($sub) => $sub
                            ->where('vehicle_id', $vehicleId)
                            ->where('ts', $maxTs)
                        );
                    }
                })
                ->get()
                ->keyBy('vehicle_id');
        }

        $vehicles = Vehicle::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get()
            ->map(function (Vehicle $v) use ($positions) {
                $pos = $positions->get($v->id);
                return [
                    'id'      => $v->id,
                    'name'    => $v->name,
                    'plate'   => $v->plate,
                    'lat'     => $pos ? (float) $pos->latitude  : null,
                    'lng'     => $pos ? (float) $pos->longitude : null,
                    'speed'   => $pos ? (float) $pos->speed_kmh : null,
                    'ts'      => $pos ? $pos->ts                 : null,
                    'has_pos' => $pos !== null,
                ];
            })
            ->values();

        return [
            'vehiclesJson' => $vehicles->toJson(),
            'withPos'      => $vehicles->where('has_pos', true)->count(),
            'total'        => $vehicles->count(),
        ];
    }
}
