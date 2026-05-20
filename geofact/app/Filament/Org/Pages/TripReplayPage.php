<?php

namespace App\Filament\Org\Pages;

use App\Models\TelemetryEvent;
use App\Models\Trip;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class TripReplayPage extends Page
{
    protected static ?string $title = 'Replay trajet';
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.org.pages.trip-replay';

    #[Url]
    public string $trip = '';

    public function mount(): void
    {
        $orgId = Auth::user()?->organization_id;

        abort_unless(
            $this->trip !== '' &&
            Trip::where('id', $this->trip)->where('organization_id', $orgId)->exists(),
            403
        );
    }

    public function getViewData(): array
    {
        $orgId = Auth::user()?->organization_id;
        $trip  = Trip::where('id', $this->trip)
            ->where('organization_id', $orgId)
            ->with(['vehicle', 'driver'])
            ->firstOrFail();

        // Relie les points GPS via la fenêtre temporelle du trajet (trip_id non renseigné sur telemetry_events)
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

        $points = $query->get(['latitude', 'longitude', 'ts', 'speed_kmh', 'event_type'])
            ->map(fn ($e) => [
                'lat'        => (float) $e->latitude,
                'lng'        => (float) $e->longitude,
                'ts'         => $e->ts,
                'speed'      => $e->speed_kmh ? (float) $e->speed_kmh : null,
                'event_type' => $e->event_type,
            ])
            ->values();

        return [
            'trip'        => $trip,
            'pointsJson'  => $points->toJson(),
            'totalPoints' => $points->count(),
        ];
    }
}

