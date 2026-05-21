<?php

namespace App\Listeners;

use App\Events\CanonicalEventReceived;
use App\Models\TelemetryEvent;
use App\Services\VehicleCurrentPositionProjector;
use Illuminate\Support\Facades\Log;

class UpdateVehicleCurrentPositionListener
{
    public function __construct(
        private readonly VehicleCurrentPositionProjector $projector
    ) {}

    public function handle(CanonicalEventReceived $event): void
    {
        $canonical = $event->event;

        if ($canonical->eventType !== 'telemetry.position.updated') {
            return;
        }

        if (empty($canonical->vehicleId)) {
            Log::warning('geofact.position_projection.skipped.no_vehicle_id', [
                'event_id' => $canonical->eventId,
            ]);
            return;
        }

        try {
            $telemetry = TelemetryEvent::where('id', $canonical->eventId)
                ->where('organization_id', $canonical->organizationId)
                ->first();

            if (! $telemetry) {
                Log::warning('geofact.position_projection.skipped.event_missing', [
                    'event_id' => $canonical->eventId,
                ]);
                return;
            }

            $this->projector->projectFromTelemetry($telemetry);
        } catch (\Throwable $e) {
            Log::warning('geofact.position_projection.failed', [
                'event_id' => $canonical->eventId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
