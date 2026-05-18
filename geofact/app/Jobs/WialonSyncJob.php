<?php

namespace App\Jobs;

use App\Connector\ConnectorPipeline;
use App\Connector\Drivers\WialonApiClient;
use App\Models\Connector;
use App\Models\WialonUnitMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Synchronise les messages Wialon vers le ConnectorPipeline IKOMA.
 * Déclenché toutes les minutes par le Scheduler (routes/console.php).
 *
 * Isolation tenant : organization_id vient toujours du Connector IKOMA, jamais de Wialon.
 * Token Wialon : lu depuis config('wialon.token') — jamais loggué en clair.
 */
class WialonSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function handle(ConnectorPipeline $pipeline): void
    {
        Log::info('geofact.wialon.sync.started');

        // Récupère tous les Connectors wialon actifs, toutes organisations confondues
        $connectors = Connector::where('provider_id', 'wialon')
            ->where('status', 'active')
            ->get();

        if ($connectors->isEmpty()) {
            Log::info('geofact.wialon.sync.no_active_connectors');
            return;
        }

        $client = new WialonApiClient();

        // Tentative de login — partagée entre tous les connectors (même token Wialon)
        try {
            $sid = $client->login();
        } catch (\Throwable $e) {
            Log::error('geofact.wialon.sync.login_failed', ['error' => $e->getMessage()]);
            return;
        }

        foreach ($connectors as $connector) {
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

        // Charge les mappings actifs de cette organisation
        $mappings = WialonUnitMapping::where('organization_id', $orgId)
            ->where('ikoma_connector_id', $connector->id)
            ->where('status', 'active')
            ->get();

        if ($mappings->isEmpty()) {
            return;
        }

        $now = now()->timestamp;

        foreach ($mappings as $mapping) {
            $this->syncUnit($pipeline, $client, $connector, $mapping, $sid, $now);
        }

        // Mise à jour last_sync_at du connector (sans passer par Eloquent pour éviter les events)
        DB::table('connectors')
            ->where('id', $connector->id)
            ->update(['last_sync_at' => now()]);
    }

    private function syncUnit(
        ConnectorPipeline $pipeline,
        WialonApiClient   $client,
        Connector         $connector,
        WialonUnitMapping $mapping,
        string            $sid,
        int               $nowTs
    ): void {
        $unitId   = $mapping->wialon_unit_id;
        $fromTs   = $mapping->last_message_ts ?? ($nowTs - config('wialon.interval', 30));
        $deviceId = 'wialon_' . $unitId;

        try {
            $messages = $client->getMessages($sid, $unitId, $fromTs, $nowTs);
        } catch (\Throwable $e) {
            Log::error('geofact.wialon.sync.get_messages_failed', [
                'unit_id' => $unitId,
                'error'   => $e->getMessage(),
            ]);
            return; // Continue avec les autres unités
        }

        if (empty($messages)) {
            return;
        }

        $lastTs      = $fromTs;
        $processed   = 0;
        $failed      = 0;

        foreach ($messages as $msg) {
            try {
                $payload = $client->flattenMessage($msg, $unitId, $deviceId);

                // Crée une Request synthétique Laravel (auth bypassée via _connector)
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

                // Injection du Connector authentifié — bypasse ConnectorAuthenticator (étape 1)
                $syntheticRequest->attributes->set('_connector', $connector);

                $pipeline->process($syntheticRequest);

                $processed++;

                // Avance le curseur temporel
                if (isset($payload['timestamp']) && (int) $payload['timestamp'] > $lastTs) {
                    $lastTs = (int) $payload['timestamp'];
                }

            } catch (\Throwable $e) {
                $failed++;
                Log::error('geofact.wialon.sync.message_failed', [
                    'unit_id'  => $unitId,
                    'msg_ts'   => $msg['t'] ?? null,
                    'error'    => $e->getMessage(),
                ]);
                // Continue — ne bloque pas les autres messages
            }
        }

        // Met à jour le curseur du mapping (uniquement si des messages ont été traités)
        if ($lastTs > $fromTs || $processed > 0) {
            $mapping->update([
                'last_message_ts' => max($lastTs, $nowTs),
            ]);
        }

        Log::info('geofact.wialon.sync.unit_done', [
            'unit_id'   => $unitId,
            'processed' => $processed,
            'failed'    => $failed,
            'messages'  => count($messages),
        ]);
    }
}
