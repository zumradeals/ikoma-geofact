<?php

namespace App\Filament\Org\Pages;

use App\Models\TelemetryEvent;
use App\Models\Trip;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TripReplayPage extends Page
{
    protected static ?string $title = 'Replay trajet';
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.org.pages.trip-replay';

    public string $tripId = '';

    public function mount(string $trip): void
    {
        $orgId = Auth::user()?->organization_id;

        // Vérification tenant
        abort_unless(
            Trip::where('id', $trip)->where('organization_id', $orgId)->exists(),
            403
        );

        $this->tripId = $trip;
    }

    public function getViewData(): array
    {
        $orgId = Auth::user()?->organization_id;
        $trip  = Trip::where('id', $this->tripId)
            ->where('organization_id', $orgId)
            ->with(['vehicle', 'driver'])
            ->firstOrFail();

        $driver = DB::connection()->getDriverName();

        // Points GPS du trajet ordonnés chronologiquement
        $points = TelemetryEvent::where('organization_id', $orgId)
            ->where('trip_id', $trip->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('ts')
            ->get(['latitude', 'longitude', 'ts', 'speed_kmh', 'event_type'])
            ->map(fn ($e) => [
                'lat'        => (float) $e->latitude,
                'lng'        => (float) $e->longitude,
                'ts'         => $e->ts,
                'speed'      => $e->speed_kmh ? (float) $e->speed_kmh : null,
                'event_type' => $e->event_type,
            ])
            ->values();

        return [
            'trip'       => $trip,
            'pointsJson' => $points->toJson(),
            'totalPoints' => $points->count(),
        ];
    }
}
