<?php

namespace App\Services;

use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Models\VehicleCurrentPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VehicleCurrentPositionProjector
{
    public function projectFromTelemetry(TelemetryEvent $event): bool
    {
        if (empty($event->vehicle_id) || empty($event->organization_id)) {
            return false;
        }

        if ($event->latitude === null || $event->longitude === null) {
            return false;
        }

        $positionTs = Carbon::parse($event->ts);

        $current = VehicleCurrentPosition::where('organization_id', $event->organization_id)
            ->where('vehicle_id', $event->vehicle_id)
            ->first();

        if ($current && $current->position_ts && Carbon::parse($current->position_ts)->gt($positionTs)) {
            Log::info('geofact.position_projection.skipped_older_event', [
                'vehicle_id'       => $event->vehicle_id,
                'current_event_id' => $current->telemetry_event_id,
                'incoming_event_id'=> $event->id,
                'current_ts'       => Carbon::parse($current->position_ts)->toIso8601String(),
                'incoming_ts'      => $positionTs->toIso8601String(),
            ]);

            return false;
        }

        $providerId = Connector::where('id', $event->connector_id)->value('provider_id');
        $payload = is_array($event->payload) ? $event->payload : [];
        $ageSeconds = max(0, (int) $positionTs->diffInSeconds(now()));

        $values = [
            'organization_id'    => $event->organization_id,
            'vehicle_id'         => $event->vehicle_id,
            'connector_id'       => $event->connector_id,
            'device_id'          => $event->device_id,
            'telemetry_event_id' => $event->id,
            'provider_id'        => $providerId,
            'provider_unit_id'   => $payload['provider_unit_id'] ?? null,
            'latitude'           => $event->latitude,
            'longitude'          => $event->longitude,
            'speed_kmh'          => $event->speed_kmh,
            'heading'            => $event->heading,
            'ignition'           => $event->ignition,
            'position_ts'        => $positionTs,
            'received_at'        => $event->received_at,
            'freshness_status'   => $this->freshnessStatus($ageSeconds),
            'source_status'      => 'official',
            'raw_age_seconds'    => $ageSeconds,
        ];

        if ($current) {
            $current->update($values);
        } else {
            VehicleCurrentPosition::create(['id' => Str::uuid()->toString()] + $values);
        }

        Log::info('geofact.position_projection.updated', [
            'vehicle_id'  => $event->vehicle_id,
            'event_id'    => $event->id,
            'position_ts' => $positionTs->toIso8601String(),
        ]);

        return true;
    }

    private function freshnessStatus(int $ageSeconds): string
    {
        return match (true) {
            $ageSeconds <= 300  => 'fresh',
            $ageSeconds <= 3600 => 'delayed',
            default             => 'stale',
        };
    }
}
