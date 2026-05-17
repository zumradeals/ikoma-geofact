<?php

namespace App\Core\Canonical;

use App\Exceptions\CanonicalValidationException;
use App\Models\Connector;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CanonicalEventFactory
{
    /**
     * Taxonomie officielle des event_type — liste fermée C-09.
     * Aucun event_type hors de cette liste n'est accepté par le Core.
     */
    private const VALID_EVENT_TYPES = [
        'telemetry.position.updated', 'telemetry.speed.updated', 'telemetry.fuel.updated',
        'telemetry.temperature.updated', 'telemetry.battery.updated',
        'telemetry.ignition.on', 'telemetry.ignition.off',
        'trip.started', 'trip.ended', 'trip.paused', 'trip.resumed', 'trip.cancelled',
        'vehicle.moving', 'vehicle.stopped', 'vehicle.idle', 'vehicle.assigned', 'vehicle.unassigned',
        'driver.trip.started', 'driver.trip.ended', 'driver.score.computed', 'driver.behavior.flagged',
        'geozone.entered', 'geozone.exited', 'geozone.violated', 'geozone.overdue',
        'alert.overspeed.detected', 'alert.harsh.braking', 'alert.harsh.acceleration',
        'alert.stop.suspicious', 'alert.stop.unauthorized', 'alert.night.activity',
        'alert.acknowledged', 'alert.resolved',
        'maintenance.threshold.reached', 'maintenance.overdue', 'maintenance.scheduled', 'maintenance.completed',
        'device.connected', 'device.disconnected', 'device.error', 'device.tampered', 'device.low.battery',
        'connector.connected', 'connector.disconnected', 'connector.sync.failed', 'connector.sync.recovered',
        'connector.replay.started', 'connector.replay.completed', 'connector.replay.failed',
        'security.device.tampered', 'security.fuel.suspected_theft', 'security.door.unauthorized_open',
        'security.battery.sabotage', 'security.unauthorized_activity', 'security.route.deviation',
        'system.kpi.computation.failed', 'system.rule.evaluation.failed', 'system.insight.generation.failed',
        'system.delivery.failed', 'system.rawstore.write.failed', 'system.contract.retired',
    ];

    /**
     * Crée un CanonicalEvent depuis un payload normalisé.
     *
     * @throws CanonicalValidationException si event_type hors taxonomie C-09
     */
    public function create(array $normalizedPayload, Connector $connector, string $rawRef): CanonicalEvent
    {
        $eventType = $normalizedPayload['event_type'] ?? null;

        if (! $eventType || ! in_array($eventType, self::VALID_EVENT_TYPES, true)) {
            throw new CanonicalValidationException(
                "event_type [{$eventType}] hors taxonomie C-09 — rejeté."
            );
        }

        $timestamp = isset($normalizedPayload['timestamp'])
            ? Carbon::parse($normalizedPayload['timestamp'])
            : now();

        return new CanonicalEvent(
            eventId:        Str::uuid()->toString(),
            eventType:      $eventType,
            connectorId:    $connector->id,
            organizationId: $connector->organization_id,
            deviceId:       $normalizedPayload['device_id'] ?? '',
            vehicleId:      $normalizedPayload['vehicle_id'] ?? null,
            tripId:         $normalizedPayload['trip_id'] ?? null,
            timestamp:      $timestamp,
            receivedAt:     now(),
            payload:        $normalizedPayload,
            missingFields:  $normalizedPayload['_missing_fields'] ?? [],
            completeness:   $normalizedPayload['_completeness'] ?? 'COMPLETE',
            rawRef:         $rawRef,
        );
    }

    public static function validEventTypes(): array
    {
        return self::VALID_EVENT_TYPES;
    }
}
