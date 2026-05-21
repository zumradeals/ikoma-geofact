<?php

namespace App\Listeners;

use App\Events\CanonicalEventReceived;
use App\Kpi\RealTime\RealTimeKpiComputer;
use Illuminate\Support\Facades\Log;

class ComputeRealTimeKpiListener
{
    public function __construct(
        private readonly RealTimeKpiComputer $computer
    ) {}

    public function handle(CanonicalEventReceived $event): void
    {
        $canonical = $event->event;

        if (empty($canonical->vehicleId)) {
            Log::info('geofact.kpi.rt.skipped.no_vehicle_id', [
                'event_id' => $canonical->eventId,
            ]);
            return;
        }

        // N'agir que sur les événements de position pour éviter une explosion
        // du nombre d'INSERTs (ignition on/off, alertes, etc. n'apportent rien)
        if ($canonical->eventType !== 'telemetry.position.updated') {
            return;
        }

        try {
            $this->computer->computeForVehicle(
                $canonical->vehicleId,
                $canonical->organizationId
            );
        } catch (\Throwable $e) {
            // Ne jamais bloquer le pipeline pour un KPI raté
            Log::warning('geofact.kpi.rt.failed', [
                'vehicle_id' => $canonical->vehicleId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
