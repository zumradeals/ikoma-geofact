<?php

namespace App\Jobs;

use App\Connector\ConnectorPipeline;
use App\Connector\Drivers\WialonApiClient;
use App\Models\Connector;
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
            return;
        }

        // Une seule requête getUnits() pour toutes les unités — inclut lmsg (dernière position)
        $units   = $client->getUnits($sid);
        $unitMap = collect($units)->keyBy('id');

        $now = now()->timestamp;

        foreach ($mappings as $mapping) {
            $unit = $unitMap->get($mapping->wialon_unit_id);
            if (! $unit) {
                Log::warning('geofact.wialon.sync.unit_not_found', ['unit_id' => $mapping->wialon_unit_id]);
                continue;
            }
            $this->syncUnitFromLastMessage($pipeline, $client, $connector, $mapping, $unit, $now);
        }

        DB::table('connectors')
            ->where('id', $connector->id)
            ->update(['last_sync_at' => now()]);
    }

    private function syncUnitFromLastMessage(
        ConnectorPipeline $pipeline,
        WialonApiClient   $client,
        Connector         $connector,
        WialonUnitMapping $mapping,
        array             $unit,
        int               $nowTs
    ): void {
        $unitId  = $mapping->wialon_unit_id;
        $lmsg    = $unit['lmsg'] ?? null;

        if (! $lmsg) {
            Log::info('geofact.wialon.sync.no_lmsg', ['unit_id' => $unitId]);
            return;
        }

        $msgTs = isset($lmsg['t']) ? (int) $lmsg['t'] : null;

        if (! $msgTs) {
            return;
        }

        // Ne pas retraiter un message déjà enregistré
        if ($mapping->last_message_ts && $msgTs <= $mapping->last_message_ts) {
            Log::info('geofact.wialon.sync.already_processed', [
                'unit_id' => $unitId,
                'msg_ts'  => $msgTs,
            ]);
            return;
        }

        $deviceId = 'wialon_' . $unitId;

        try {
            $payload               = $client->flattenMessage($lmsg, $unitId, $deviceId);
            $payload['event_type'] = 'telemetry.position.updated';

            if ($mapping->ikoma_vehicle_id) {
                $payload['vehicle_id'] = $mapping->ikoma_vehicle_id;
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

            Log::info('geofact.wialon.sync.unit_done', [
                'unit_id'    => $unitId,
                'msg_ts'     => $msgTs,
                'lat'        => $payload['latitude'] ?? null,
                'lon'        => $payload['longitude'] ?? null,
                'speed_kmh'  => $payload['speed_kmh'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('geofact.wialon.sync.unit_failed', [
                'unit_id' => $unitId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
