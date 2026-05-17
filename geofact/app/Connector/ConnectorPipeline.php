<?php

namespace App\Connector;

use App\Connector\Auth\ConnectorAuthenticator;
use App\Connector\Deduplication\TransportDeduplicator;
use App\Connector\Normalizer\FieldNormalizer;
use App\Core\Deduplication\CanonicalDeduplicator;
use App\Core\Canonical\CanonicalEventFactory;
use App\Core\Canonical\CompletenessEvaluator;
use App\Core\TenantResolver;
use App\Events\CanonicalEventReceived;
use App\Exceptions\CanonicalValidationException;
use App\Exceptions\ConnectorAuthException;
use App\Models\Connector;
use App\Models\RawStore;
use App\Models\TelemetryEvent;
use App\RawStore\RawStoreWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConnectorPipeline
{
    public function __construct(
        private readonly ConnectorAuthenticator  $authenticator,
        private readonly RawStoreWriter          $rawStoreWriter,
        private readonly TransportDeduplicator   $transportDedup,
        private readonly FieldNormalizer         $normalizer,
        private readonly CompletenessEvaluator   $completenessEvaluator,
        private readonly CanonicalEventFactory   $canonicalFactory,
        private readonly CanonicalDeduplicator   $canonicalDedup,
        private readonly TenantResolver          $tenantResolver,
    ) {}

    /**
     * Pipeline Connector — 10 étapes dans l'ordre strict (C-10, arborescence technique).
     *
     * Étape 1  : Authentification Connector
     * Étape 2  : Écriture Raw Store
     * Étape 3  : Déduplication transport
     * Étape 4  : Normalisation champs
     * Étape 5  : Évaluation complétude (CCS + CCM)
     * Étape 6  : Création CanonicalEvent
     * Étape 7  : Déduplication canonique
     * Étape 8  : Résolution tenant si vehicle_id absent
     * Étape 9  : INSERT TelemetryEvent
     * Étape 10 : Dispatch CanonicalEventReceived
     *
     * @return bool true si l'événement a atteint le Core, false si arrêté avant
     */
    public function process(Request $request): bool
    {
        $rawPayload = $request->getContent();

        // ── Étape 1 : Auth Connector ───────────────────────────────────────────
        // Si le middleware a déjà authentifié (X-Connector-Token ou JWT Bearer),
        // réutiliser le connector injecté plutôt que de re-authentifier.
        $connector = $request->get('_connector');
        if (! $connector instanceof Connector) {
            try {
                $connector = $this->authenticator->authenticate($request);
            } catch (ConnectorAuthException $e) {
                Log::warning('geofact.pipeline.auth_failed', ['error' => $e->getMessage()]);
                throw $e; // Rien n'est écrit — stop total (C-10)
            }
        }

        // ── Étape 2 : Raw Store ────────────────────────────────────────────────
        $format = $this->guessFormat($request);
        $rawRef = $this->rawStoreWriter->write(
            $connector->id,
            $connector->organization_id,
            $rawPayload,
            $format,
            'OK'
        );

        // ── Étape 3 : Déduplication transport ─────────────────────────────────
        $decoded   = json_decode($rawPayload, true) ?? [];
        $deviceId  = $decoded['device_id'] ?? $decoded['imei'] ?? '';
        $timestamp = $decoded['timestamp'] ?? $decoded['ts'] ?? $decoded['deviceTime'] ?? '';

        if ($this->transportDedup->isDuplicate($connector->id, $rawPayload, $deviceId, $timestamp)) {
            $this->rawStoreWriter->updateFlag($rawRef, 'DUPLICATE_TRANSPORT');
            Log::info('geofact.pipeline.stopped.duplicate_transport', ['raw_ref' => $rawRef]);
            return false;
        }

        // ── Étape 4 : Normalisation ────────────────────────────────────────────
        $normalized = $this->normalizer->normalize($decoded, $connector->provider_id);

        // ── Étape 5 : Évaluation complétude ───────────────────────────────────
        $completeness = $this->completenessEvaluator->evaluate($normalized, $connector->organization_id);

        if ($completeness['completeness'] === 'REJECTED') {
            $this->rawStoreWriter->updateFlag($rawRef, 'REJECTED');
            Log::warning('geofact.pipeline.stopped.rejected', [
                'raw_ref'        => $rawRef,
                'missing_fields' => $completeness['missing_fields'],
            ]);
            return false;
        }

        // Enrichir le payload avec les métadonnées de complétude
        $normalized['_completeness']   = $completeness['completeness'];
        $normalized['_missing_fields'] = $completeness['missing_fields'];

        // ── Étape 6 : Création CanonicalEvent ─────────────────────────────────
        try {
            $canonicalEvent = $this->canonicalFactory->create($normalized, $connector, $rawRef);
        } catch (CanonicalValidationException $e) {
            $this->rawStoreWriter->updateFlag($rawRef, 'REJECTED');
            Log::error('geofact.pipeline.stopped.canonical_validation', [
                'raw_ref' => $rawRef,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }

        // ── Étape 7 : Déduplication canonique ─────────────────────────────────
        if ($this->canonicalDedup->isDuplicate($canonicalEvent->eventId)) {
            Log::info('geofact.pipeline.stopped.duplicate_canonical', [
                'event_id' => $canonicalEvent->eventId,
            ]);
            return false;
        }

        // ── Étape 8 : Résolution tenant si vehicle_id absent ──────────────────
        $vehicleId = $canonicalEvent->vehicleId;
        $fleetId   = $normalized['fleet_id'] ?? null;

        if (! $vehicleId && $canonicalEvent->deviceId) {
            $resolved  = $this->tenantResolver->resolve($canonicalEvent->deviceId);
            $vehicleId = $resolved['vehicle_id'];
            $fleetId   = $resolved['fleet_id'];
        }

        // ── Étape 9 : INSERT TelemetryEvent ───────────────────────────────────
        try {
            TelemetryEvent::create([
                'id'                  => $canonicalEvent->eventId,
                'connector_id'        => $canonicalEvent->connectorId,
                'device_id'           => $canonicalEvent->deviceId,
                'vehicle_id'          => $vehicleId,
                'organization_id'     => $canonicalEvent->organizationId,
                'trip_id'             => $canonicalEvent->tripId,
                'event_type'          => $canonicalEvent->eventType,
                'ts'                  => $canonicalEvent->timestamp,
                'received_at'         => $canonicalEvent->receivedAt,
                'latitude'            => $normalized['latitude'] ?? null,
                'longitude'           => $normalized['longitude'] ?? null,
                'speed_kmh'           => $normalized['speed_kmh'] ?? null,
                'heading'             => $normalized['heading'] ?? null,
                'altitude_m'          => $normalized['altitude_m'] ?? null,
                'fuel_level_pct'      => $normalized['fuel_level_pct'] ?? null,
                'temperature_celsius' => $normalized['temperature_celsius'] ?? null,
                'ignition'            => $normalized['ignition'] ?? null,
                'payload'             => $normalized,
                'completeness'        => $canonicalEvent->completeness,
                'missing_fields'      => $canonicalEvent->missingFields ?: null,
                'raw_ref'             => $rawRef,
            ]);

            // Mise à jour Raw Store : lier le canonical_ref
            $this->rawStoreWriter->updateFlag($rawRef, 'OK', $canonicalEvent->eventId);

            // Mise à jour last_sync_at du connector
            \Illuminate\Support\Facades\DB::table('connectors')
                ->where('id', $connector->id)
                ->update(['last_sync_at' => now()]);

        } catch (\Throwable $e) {
            Log::error('geofact.pipeline.telemetry_insert_failed', [
                'event_id' => $canonicalEvent->eventId,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }

        // ── Étape 10 : Dispatch vers le Core ──────────────────────────────────
        // Le Connector ne connaît PAS le Rules Engine — il dispatch uniquement (C-10)
        event(new CanonicalEventReceived($canonicalEvent));

        Log::info('geofact.pipeline.completed', [
            'event_id'   => $canonicalEvent->eventId,
            'event_type' => $canonicalEvent->eventType,
            'raw_ref'    => $rawRef,
        ]);

        return true;
    }

    /**
     * Rejoue un événement depuis le Raw Store (C-01.3 Replay).
     * Même pipeline que process() mais sans ré-écriture Raw Store.
     */
    public function processReplay(RawStore $raw, Connector $connector): bool
    {
        $decoded    = json_decode($raw->payload_raw, true) ?? [];
        $normalized = $this->normalizer->normalize($decoded, $connector->provider_id);

        $completeness = $this->completenessEvaluator->evaluate($normalized, $connector->organization_id);

        if ($completeness['completeness'] === 'REJECTED') {
            return false;
        }

        $normalized['_completeness']   = $completeness['completeness'];
        $normalized['_missing_fields'] = $completeness['missing_fields'];

        try {
            $canonicalEvent = $this->canonicalFactory->create($normalized, $connector, $raw->id);
        } catch (CanonicalValidationException) {
            return false;
        }

        if ($this->canonicalDedup->isDuplicate($canonicalEvent->eventId)) {
            return false;
        }

        $vehicleId = $canonicalEvent->vehicleId;
        $fleetId   = null;

        if (! $vehicleId && $canonicalEvent->deviceId) {
            $resolved  = $this->tenantResolver->resolve($canonicalEvent->deviceId);
            $vehicleId = $resolved['vehicle_id'];
            $fleetId   = $resolved['fleet_id'];
        }

        TelemetryEvent::create([
            'id'                  => $canonicalEvent->eventId,
            'connector_id'        => $canonicalEvent->connectorId,
            'device_id'           => $canonicalEvent->deviceId,
            'vehicle_id'          => $vehicleId,
            'organization_id'     => $canonicalEvent->organizationId,
            'trip_id'             => $canonicalEvent->tripId,
            'event_type'          => $canonicalEvent->eventType,
            'ts'                  => $canonicalEvent->timestamp,
            'received_at'         => $canonicalEvent->receivedAt,
            'latitude'            => $normalized['latitude'] ?? null,
            'longitude'           => $normalized['longitude'] ?? null,
            'speed_kmh'           => $normalized['speed_kmh'] ?? null,
            'heading'             => $normalized['heading'] ?? null,
            'altitude_m'          => $normalized['altitude_m'] ?? null,
            'fuel_level_pct'      => $normalized['fuel_level_pct'] ?? null,
            'temperature_celsius' => $normalized['temperature_celsius'] ?? null,
            'ignition'            => $normalized['ignition'] ?? null,
            'payload'             => $normalized,
            'completeness'        => $canonicalEvent->completeness,
            'missing_fields'      => $canonicalEvent->missingFields ?: null,
            'raw_ref'             => $raw->id,
        ]);

        event(new CanonicalEventReceived($canonicalEvent));

        return true;
    }

    private function guessFormat(Request $request): string
    {
        $contentType = $request->header('Content-Type', '');
        return match(true) {
            str_contains($contentType, 'json') => 'json',
            str_contains($contentType, 'xml')  => 'xml',
            str_contains($contentType, 'csv')  => 'csv',
            default                             => 'unknown',
        };
    }
}
