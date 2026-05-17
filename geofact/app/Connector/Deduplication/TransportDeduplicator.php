<?php

namespace App\Connector\Deduplication;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransportDeduplicator
{
    private const WINDOW_SECONDS = 300; // 5 minutes
    private const CACHE_PREFIX   = 'geofact:transport_dedup:';

    /**
     * Détecte les doublons de transport avant écriture Raw Store.
     * Contrat C-10.5 : même payload brut reçu deux fois dans la fenêtre → rejet silencieux.
     *
     * Utilise un hash MD5 du triplet (payload + device_id + timestamp) comme clé de cache.
     */
    public function isDuplicate(
        string $connectorId,
        string $payload,
        string $deviceId,
        string $timestamp
    ): bool {
        $fingerprint = md5($payload . $deviceId . $timestamp);
        $cacheKey    = self::CACHE_PREFIX . $connectorId . ':' . $fingerprint;

        if (Cache::has($cacheKey)) {
            Log::info('geofact.connector.dedup.transport_duplicate_detected', [
                'connector_id' => $connectorId,
                'device_id'    => $deviceId,
                'fingerprint'  => $fingerprint,
            ]);
            return true;
        }

        // Marque comme vu pour la durée de la fenêtre
        Cache::put($cacheKey, true, self::WINDOW_SECONDS);
        return false;
    }
}
