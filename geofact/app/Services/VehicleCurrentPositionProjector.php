<?php

namespace App\Services;

use App\Models\Connector;
use App\Models\TelemetryEvent;
use App\Models\VehicleCurrentPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

    /**
     * Met à jour VehicleCurrentPosition directement depuis last_pos Wialon,
     * sans passer par le pipeline (TelemetryEvent non créé).
     * Appelé à chaque sync pour garantir la fraîcheur même quand le pipeline
     * est bloqué par déduplication ou avancement last_message_ts.
     */
    public function projectFromLastPos(
        string $organizationId,
        string $vehicleId,
        string $connectorId,
        string $deviceId,
        array  $lastPos
    ): bool {
        $lat = isset($lastPos['lat']) ? (float) $lastPos['lat'] : null;
        $lon = isset($lastPos['lon']) ? (float) $lastPos['lon'] : null;
        $ts  = isset($lastPos['ts'])  ? (int)   $lastPos['ts']  : null;

        if ($lat === null || $lon === null || ! $ts) {
            return false;
        }

        if ($lat === 0.0 && $lon === 0.0) {
            return false;
        }

        $positionTs = Carbon::createFromTimestamp($ts);

        $current = VehicleCurrentPosition::where('organization_id', $organizationId)
            ->where('vehicle_id', $vehicleId)
            ->first();

        if ($current && $current->position_ts && Carbon::parse($current->position_ts)->gte($positionTs)) {
            return false;
        }

        $providerId = DB::table('connectors')->where('id', $connectorId)->value('provider_id');
        $ageSeconds = max(0, (int) $positionTs->diffInSeconds(now()));

        $values = [
            'organization_id'  => $organizationId,
            'vehicle_id'       => $vehicleId,
            'connector_id'     => $connectorId,
            'device_id'        => $deviceId,
            'provider_id'      => $providerId ?? 'wialon',
            'provider_unit_id' => $lastPos['provider_unit_id'] ?? null,
            'latitude'         => $lat,
            'longitude'        => $lon,
            'speed_kmh'        => isset($lastPos['speed']) ? (float) $lastPos['speed'] : null,
            'heading'          => null,
            'ignition'         => null,
            'position_ts'      => $positionTs,
            'received_at'      => now(),
            'freshness_status' => $this->freshnessStatus($ageSeconds),
            'source_status'    => 'official',
            'raw_age_seconds'  => $ageSeconds,
        ];

        if ($current) {
            $current->update($values);
        } else {
            VehicleCurrentPosition::create(['id' => Str::uuid()->toString()] + $values);
        }

        Log::info('geofact.position_projection.direct_upsert', [
            'vehicle_id'  => $vehicleId,
            'position_ts' => $positionTs->toIso8601String(),
            'age_seconds' => $ageSeconds,
            'source'      => 'wialon_last_pos',
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
