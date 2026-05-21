<?php

namespace App\Jobs;

use App\Connector\ConnectorPipeline;
use App\Connector\Drivers\WialonApiClient;
use App\Models\Connector;
use App\Models\Device;
use App\Models\WialonUnitMapping;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Synchronise les positions Wialon vers le ConnectorPipeline IKOMA.
 * Déclenché toutes les minutes par le Scheduler (routes/console.php).
 * Exécution synchrone (pas de ShouldQueue) — pas de worker requis.
 *
 * Stratégie : utilise lmsg (dernière position connue) de getUnits() plutôt que
 * getMessages(), car messages/load_interval nécessite une permission spéciale
 * et renvoie 0 résultats si les véhicules sont à l'arrêt.
 *
 * Isolation tenant : organization_id vient toujours du Connector IKOMA, jamais de Wialon.
 * Token Wialon : lu depuis provider_config — jamais loggué en clair.
 */
class WialonSyncJob
{
    use Dispatchable;

    public function handle(ConnectorPipeline $pipeline): void
    {
        Log::info('geofact.wialon.sync.started');

        $connectors = Connector::where('provider_id', 'wialon')
            ->where('status', 'active')
            ->get();

        if ($connectors->isEmpty()) {
            Log::info('geofact.wialon.sync.no_active_connectors');
            return;
        }

        foreach ($connectors as $connector) {
            $wialonToken   = $connector->getProviderConfigValue('wialon_token');
            $wialonBaseUrl = $connector->getProviderConfigValue('wialon_base_url');

            if (empty($wialonToken)) {
                Log::warning('geofact.wialon.sync.missing_token', ['connector_id' => $connector->id]);
                continue;
            }

            $client = new WialonApiClient($wialonToken, $wialonBaseUrl);

            try {
                $sid = $client->login();
            } catch (\Throwable $e) {
                Log::error('geofact.wialon.sync.login_failed', [
                    'connector_id' => $connector->id,
                    'error'        => $e->getMessage(),
                ]);
                continue;
            }

            $this->syncConnector($pipeline, $client, $connector, $sid);
        }

        Log::info('geofact.wialon.sync.completed', ['connectors' => $connectors->count()]);
    }

    private function syncConnector(
        ConnectorPipeline $pipeline,
        WialonApiClient   $client,
        Connector         $connector,
        string            $sid
    ): void {
        $orgId = $connector->organization_id;

        $mappings = WialonUnitMapping::where('organization_id', $orgId)
            ->where('ikoma_connector_id', $connector->id)
            ->where('status', 'active')
            ->get();

        if ($mappings->isEmpty()) {
            Log::warning('geofact.wialon.sync.no_mappings', ['connector_id' => $connector->id, 'org_id' => $orgId]);
            return;
        }

        // Une seule requête getUnits() pour toutes les unités — inclut lmsg (dernière position)
        $units   = $client->getUnits($sid);
        $unitMap = collect($units)->keyBy('id');

        Log::info('geofact.wialon.sync.mappings_found', ['count' => $mappings->count(), 'units_from_api' => count($units)]);

        $now = now()->timestamp;

        foreach ($mappings as $mapping) {
            $unit = $unitMap->get($mapping->wialon_unit_id);
            if (! $unit) {
                Log::warning('geofact.wialon.sync.unit_not_found', ['unit_id' => $mapping->wialon_unit_id]);
                continue;
            }
            $this->syncUnitWithHistory($pipeline, $client, $connector, $mapping, $unit, $sid, $now);
        }

        DB::table('connectors')
            ->where('id', $connector->id)
            ->update(['last_sync_at' => now()]);
    }

    /**
     * Synchronise un véhicule en utilisant getMessages() pour récupérer tous les points GPS
     * depuis le dernier sync (pas seulement lmsg). Fallback sur lmsg si getMessages échoue.
     *
     * Stratégie full-history : essentielle pour le TripDetector qui a besoin de plusieurs
     * points GPS consécutifs pour calculer la distance réelle d'un trajet.
     */
    private function syncUnitWithHistory(
        ConnectorPipeline $pipeline,
        WialonApiClient   $client,
        Connector         $connector,
        WialonUnitMapping $mapping,
        array             $unit,
        string            $sid,
        int               $nowTs
    ): void {
        $unitId = $mapping->wialon_unit_id;

        // Garantit l'existence d'un Device (FK obligatoire)
        $device = Device::firstOrCreate(
            [
                'organization_id' => $connector->organization_id,
                'imei'            => 'wialon_' . $unitId,
            ],
            [
                'id'          => \Illuminate\Support\Str::uuid()->toString(),
                'vehicle_id'  => $mapping->ikoma_vehicle_id,
                'provider_id' => 'wialon',
                'status'      => 'active',
                'created_by'  => null,
            ]
        );

        // Fenêtre de temps : depuis le dernier message traité, max 24h en arrière pour rattraper le retard
        $fromTs = $mapping->last_message_ts
            ? ((int) $mapping->last_message_ts + 1)
            : ($nowTs - 86400);

        // Tente getMessages() pour récupérer tous les points GPS de la période
        $messages = $client->getMessages($sid, (int) $unitId, $fromTs, $nowTs);

        if (! empty($messages)) {
            $latestTs    = null; // ts max des messages GPS traités
            $latestAnyTs = null; // ts max de tous les messages (GPS ou non)
            $processed   = 0;

            foreach ($messages as $msg) {
                $msgTs = isset($msg['t']) ? (int) $msg['t'] : null;
                if (! $msgTs) {
                    continue;
                }

                if ($latestAnyTs === null || $msgTs > $latestAnyTs) {
                    $latestAnyTs = $msgTs;
                }

                // Ignorer les messages sans position GPS
                if (empty($msg['pos'])) {
                    continue;
                }

                try {
                    $payload               = $client->flattenMessage($msg, (int) $unitId, $device->id);
                    $payload['event_type'] = 'telemetry.position.updated';
                    $payload['device_id']  = $device->id;

                    if ($mapping->ikoma_vehicle_id) {
                        $payload['vehicle_id'] = $mapping->ikoma_vehicle_id;
                    }

                    // Guard : bloquer les événements orphelins sans vehicle_id
                    if (empty($payload['vehicle_id'])) {
                        Log::warning('geofact.wialon.sync.orphan_event_blocked', [
                            'unit_id'   => $unitId,
                            'msg_ts'    => $msgTs,
                            'device_id' => $device->id,
                            'reason'    => 'vehicle_id absent — mapping ikoma_vehicle_id vide',
                        ]);
                        continue;
                    }

                    $syntheticRequest = Request::create(
                        '/wialon/ingest',
                        'POST',
                        [],
                        [],
                        [],
                        ['CONTENT_TYPE' => 'application/json'],
                        json_encode($payload)
                    );
                    $syntheticRequest->headers->set('Content-Type', 'application/json');
                    $syntheticRequest->attributes->set('_connector', $connector);

                    $pipeline->process($syntheticRequest);
                    $processed++;

                    if ($latestTs === null || $msgTs > $latestTs) {
                        $latestTs = $msgTs;
                    }
                } catch (\Throwable $e) {
                    Log::error('geofact.wialon.sync.message_failed', [
                        'unit_id' => $unitId,
                        'msg_ts'  => $msgTs,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            if ($latestTs) {
                $mapping->update(['last_message_ts' => $latestTs]);
                Log::info('geofact.wialon.sync.history_done', [
                    'unit_id'   => $unitId,
                    'processed' => $processed,
                    'from_ts'   => $fromTs,
                    'to_ts'     => $nowTs,
                ]);
                return;
            }

            // Aucun message GPS dans le lot : avancer last_message_ts jusqu'au
            // ts maximal des messages non-GPS pour rétrécir la fenêtre du prochain
            // appel et éviter que loadCount:500 soit saturé par des heartbeats.
            if ($latestAnyTs && $latestAnyTs > (int) $mapping->last_message_ts) {
                $mapping->update(['last_message_ts' => $latestAnyTs]);
                Log::info('geofact.wialon.sync.advanced_past_non_gps', [
                    'unit_id'        => $unitId,
                    'msg_count'      => count($messages),
                    'latest_any_ts'  => $latestAnyTs,
                ]);
            } else {
                Log::info('geofact.wialon.sync.no_gps_in_messages', [
                    'unit_id'   => $unitId,
                    'msg_count' => count($messages),
                    'from_ts'   => $fromTs,
                ]);
            }
        }

        // Fallback lmsg si getMessages() retourne vide (permission insuffisante ou aucun mouvement)
        $lmsg  = $unit['lmsg'] ?? null;
        $msgTs = isset($lmsg['t']) ? (int) $lmsg['t'] : null;

        if (! $lmsg || ! $msgTs) {
            Log::info('geofact.wialon.sync.no_data', ['unit_id' => $unitId]);
            return;
        }

        if ($mapping->last_message_ts && $msgTs <= $mapping->last_message_ts) {
            return;
        }

        try {
            $payload               = $client->flattenMessage($lmsg, (int) $unitId, $device->id);
            $payload['event_type'] = 'telemetry.position.updated';
            $payload['device_id']  = $device->id;

            if ($mapping->ikoma_vehicle_id) {
                $payload['vehicle_id'] = $mapping->ikoma_vehicle_id;
            }

            // Guard : bloquer les événements orphelins sans vehicle_id
            if (empty($payload['vehicle_id'])) {
                Log::warning('geofact.wialon.sync.orphan_event_blocked', [
                    'unit_id'   => $unitId,
                    'msg_ts'    => $msgTs,
                    'device_id' => $device->id,
                    'reason'    => 'vehicle_id absent — mapping ikoma_vehicle_id vide',
                ]);
                return;
            }

            $syntheticRequest = Request::create(
                '/wialon/ingest',
                'POST',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($payload)
            );
            $syntheticRequest->headers->set('Content-Type', 'application/json');
            $syntheticRequest->attributes->set('_connector', $connector);

            $pipeline->process($syntheticRequest);
            $mapping->update(['last_message_ts' => $msgTs]);

            Log::info('geofact.wialon.sync.lmsg_fallback_done', [
                'unit_id'   => $unitId,
                'msg_ts'    => $msgTs,
                'speed_kmh' => $payload['speed_kmh'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('geofact.wialon.sync.unit_failed', [
                'unit_id' => $unitId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
