<?php

namespace App\Core\Canonical;

use Carbon\Carbon;

/**
 * Objet immuable représentant un Canonical Event GEOFACT.
 * Contrat C-01 : seul objet accepté par le Core — produit exclusivement par le Connector Layer.
 */
readonly class CanonicalEvent
{
    public function __construct(
        public string  $eventId,
        public string  $eventType,
        public string  $connectorId,
        public string  $organizationId,
        public string  $deviceId,
        public ?string $vehicleId,
        public ?string $tripId,
        public Carbon  $timestamp,
        public Carbon  $receivedAt,
        public array   $payload,
        public array   $missingFields,
        public string  $completeness,   // COMPLETE | INCOMPLETE | REJECTED
        public string  $rawRef,         // référence Raw Store
    ) {}
}
