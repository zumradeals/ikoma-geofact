<?php

namespace App\Connector\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP pour l'API distante Wialon.
 * Ce client ne connaît pas ConnectorPipeline — il expose uniquement les données brutes.
 * Le token n'est jamais loggué en clair (contrat de sécurité absolu).
 */
class WialonApiClient
{
    private string $baseUrl;
    private string $token;

    /**
     * @param string|null $token   Token Wialon — priorité : paramètre > config('wialon.token')
     * @param string|null $baseUrl URL de base — priorité : paramètre > config('wialon.base_url')
     */
    public function __construct(?string $token = null, ?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('wialon.base_url', 'https://hosting.wialon.com'), '/');
        $this->token   = $token ?? (string) config('wialon.token', '');
    }

    /**
     * Ouvre une session Wialon et retourne le session ID (eid).
     *
     * @throws \RuntimeException si l'authentification échoue
     */
    public function login(): string
    {
        $token = $this->token;

        if (empty($token)) {
            Log::error('geofact.wialon.login.missing_token');
            throw new \RuntimeException('Token Wialon non configuré pour ce connecteur.');
        }

        try {
            $response = Http::timeout(15)->get($this->baseUrl . '/wialon/ajax.html', [
                'svc'    => 'token/login',
                'params' => json_encode(['token' => $token]),
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.wialon.login.http_error', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Échec connexion Wialon : ' . $e->getMessage());
        }

        if (! $response->successful()) {
            Log::error('geofact.wialon.login.bad_status', ['status' => $response->status()]);
            throw new \RuntimeException('Wialon HTTP ' . $response->status());
        }

        $body = $response->json();

        if (empty($body['eid'])) {
            // Logger le code d'erreur Wialon sans exposer le token
            Log::error('geofact.wialon.login.no_eid', [
                'error_code' => $body['error'] ?? null,
                'reason'     => $body['reason'] ?? null,
            ]);
            throw new \RuntimeException('Wialon : session ID absent (code=' . ($body['error'] ?? '?') . ')');
        }

        Log::info('geofact.wialon.login.success');

        return (string) $body['eid'];
    }

    /**
     * Liste toutes les unités Wialon accessibles via cette session.
     *
     * @return array<int, array{id: int, name: string, lastMessage: array|null}>
     */
    public function getUnits(string $sid): array
    {
        try {
            $response = Http::timeout(20)->get($this->baseUrl . '/wialon/ajax.html', [
                'svc'    => 'core/search_items',
                'params' => json_encode([
                    'spec' => [
                        'itemsType'    => 'avl_unit',
                        'propName'     => '*',
                        'propValueMask'=> '*',
                        'sortType'     => 'sys_name',
                    ],
                    'force'     => 1,
                    'flags'     => 1,
                    'from'      => 0,
                    'to'        => 0,
                ]),
                'sid' => $sid,
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.wialon.get_units.http_error', ['error' => $e->getMessage()]);
            return [];
        }

        if (! $response->successful()) {
            Log::error('geofact.wialon.get_units.bad_status', ['status' => $response->status()]);
            return [];
        }

        $body  = $response->json();
        $items = $body['items'] ?? [];

        $units = [];
        foreach ($items as $item) {
            $units[] = [
                'id'          => (int) ($item['id'] ?? 0),
                'name'        => (string) ($item['nm'] ?? ''),
                'lastMessage' => $item['lmsg'] ?? null,
            ];
        }

        Log::info('geofact.wialon.get_units.success', ['count' => count($units)]);

        return $units;
    }

    /**
     * Récupère les messages d'une unité Wialon sur un intervalle de temps.
     *
     * @return array<int, array> Messages bruts Wialon
     */
    public function getMessages(string $sid, int $unitId, int $fromTs, int $toTs): array
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/wialon/ajax.html', [
                'svc'    => 'messages/load_interval',
                'params' => json_encode([
                    'itemId'    => $unitId,
                    'timeFrom'  => $fromTs,
                    'timeTo'    => $toTs,
                    'flags'     => 0,
                    'flagsMask' => 0,
                    'loadCount' => 500,
                ]),
                'sid' => $sid,
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.wialon.get_messages.http_error', [
                'unit_id' => $unitId,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }

        if (! $response->successful()) {
            Log::error('geofact.wialon.get_messages.bad_status', [
                'unit_id' => $unitId,
                'status'  => $response->status(),
                'body'    => substr($response->body(), 0, 300),
            ]);
            return [];
        }

        $body = $response->json();

        Log::debug('geofact.wialon.get_messages.raw', [
            'unit_id' => $unitId,
            'keys'    => array_keys($body ?? []),
            'error'   => $body['error'] ?? null,
            'count'   => $body['count'] ?? null,
        ]);

        // Wialon retourne {count: N, messages: [...]} ou une erreur {error: N}
        if (isset($body['error'])) {
            Log::warning('geofact.wialon.get_messages.api_error', [
                'unit_id'    => $unitId,
                'error_code' => $body['error'],
            ]);
            return [];
        }

        return $body['messages'] ?? [];
    }

    /**
     * Aplatit un message Wialon brut vers un tableau compatible NormalizationMap.
     *
     * Format Wialon :
     *   { "t": 1621234567, "tp": "ud", "pos": { "y": lat, "x": lon, "c": cap, "z": alt, "s": spd },
     *     "i": 0, "p": { "can_fuel_litres": 60.5, "engine_temp": 85 } }
     *
     * Résultat aplati (clés correspondant au mapping NormalizationMap 'wialon') :
     *   { "timestamp": ..., "latitude": ..., "longitude": ..., "speed_kmh": ...,
     *     "heading": ..., "altitude_m": ..., "fuel_level_pct": ...,
     *     "temperature_celsius": ..., "ignition": ..., "device_id": "..." }
     */
    public function flattenMessage(array $msg, int $unitId, string $deviceId): array
    {
        $pos = $msg['pos'] ?? [];
        $p   = $msg['p']   ?? [];

        $flat = [
            'timestamp' => isset($msg['t']) ? (int) $msg['t'] : null,
            'device_id' => $deviceId,
        ];

        // Coordonnées et cinématique depuis pos
        if (isset($pos['y'])) {
            $flat['latitude'] = (float) $pos['y'];
        }
        if (isset($pos['x'])) {
            $flat['longitude'] = (float) $pos['x'];
        }
        if (isset($pos['s'])) {
            $flat['speed_kmh'] = (float) $pos['s'];
        }
        if (isset($pos['c'])) {
            $flat['heading'] = (int) $pos['c'];
        }
        if (isset($pos['z'])) {
            $flat['altitude_m'] = (float) $pos['z'];
        }

        // Champs paramétriques depuis p
        if (isset($p['can_fuel_litres'])) {
            $flat['fuel_level_pct'] = (float) $p['can_fuel_litres'];
        }
        if (isset($p['engine_temp'])) {
            $flat['temperature_celsius'] = (float) $p['engine_temp'];
        }

        // Ignition : champ i est un bitfield — bit 0 = ignition
        if (isset($msg['i'])) {
            $flat['ignition'] = ((int) $msg['i'] & 1) === 1;
        }

        return $flat;
    }
}
