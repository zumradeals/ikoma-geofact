<?php

namespace App\RawStore;

use App\Connector\ConnectorPipeline;
use App\Models\Connector;
use App\Models\RawStore;
use Illuminate\Support\Facades\Log;

class RawStoreReplayer
{
    public function __construct(private readonly ConnectorPipeline $pipeline) {}

    /**
     * Rejoue un événement rejeté depuis le Raw Store.
     * Contrat C-01.3 : le Replay permet le retraitement après correction du Connector.
     *
     * Génère connector.replay.started → retraitement → connector.replay.completed|failed
     */
    public function replay(string $rawStoreId): bool
    {
        $raw = RawStore::find($rawStoreId);

        if (! $raw) {
            Log::error('geofact.replay.not_found', ['raw_store_id' => $rawStoreId]);
            return false;
        }

        if (! in_array($raw->flag, ['REJECTED', 'CORRUPTED'])) {
            Log::warning('geofact.replay.invalid_flag', [
                'raw_store_id' => $rawStoreId,
                'flag'         => $raw->flag,
                'reason'       => 'Seuls REJECTED et CORRUPTED sont rejouables.',
            ]);
            return false;
        }

        $connector = Connector::find($raw->connector_id);

        if (! $connector || $connector->status !== 'active') {
            Log::warning('geofact.replay.connector_not_active', [
                'raw_store_id' => $rawStoreId,
                'connector_id' => $raw->connector_id,
            ]);
            return false;
        }

        Log::info('geofact.connector.replay.started', [
            'raw_store_id' => $rawStoreId,
            'connector_id' => $raw->connector_id,
        ]);

        try {
            $result = $this->pipeline->processReplay($raw, $connector);

            if ($result) {
                Log::info('geofact.connector.replay.completed', [
                    'raw_store_id' => $rawStoreId,
                ]);
                return true;
            }

            Log::warning('geofact.connector.replay.failed', [
                'raw_store_id' => $rawStoreId,
                'reason'       => 'Pipeline a retourné false.',
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('geofact.connector.replay.failed', [
                'raw_store_id' => $rawStoreId,
                'error'        => $e->getMessage(),
            ]);
            return false;
        }
    }
}
