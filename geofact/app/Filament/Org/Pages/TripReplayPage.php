<?php

namespace App\Filament\Org\Pages;

use App\Models\TelemetryEvent;
use App\Models\Trip;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TripReplayPage extends Page
{
    protected static ?string $title = 'Replay trajet';
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.org.pages.trip-replay';

    public string $tripId = '';

    public function mount(): void
    {
        $tripParam = request()->query('trip', '');
        $orgId     = Auth::user()?->organization_id;

        abort_unless(
            $tripParam !== '' &&
            Trip::where('id', $tripParam)->where('organization_id', $orgId)->exists(),
            403
        );

        $this->tripId = $tripParam;
    }

    public function getViewData(): array
    {
        $orgId = Auth::user()?->organization_id;
        $trip  = Trip::where('id', $this->tripId)
            ->where('organization_id', $orgId)
            ->with(['vehicle', 'driver'])
            ->firstOrFail();

        $query = TelemetryEvent::where('vehicle_id', $trip->vehicle_id)
            ->where('organization_id', $orgId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('ts');

        if ($trip->started_at && $trip->ended_at) {
            $query->whereBetween('ts', [$trip->started_at, $trip->ended_at]);
        } elseif ($trip->started_at) {
            $query->where('ts', '>=', $trip->started_at);
        }

        $points = $query->get(['latitude', 'longitude', 'ts', 'speed_kmh'])
            ->map(fn ($e) => [
                'lat'   => (float) $e->latitude,
                'lng'   => (float) $e->longitude,
                'ts'    => $e->ts instanceof \Carbon\Carbon ? $e->ts->toIso8601String() : (string) $e->ts,
                'speed' => $e->speed_kmh ? (float) $e->speed_kmh : null,
            ])
            ->values();

        return [
            'trip'        => $trip,
            'pointsJson'  => $points->toJson(),
            'totalPoints' => $points->count(),
        ];
    }
}


