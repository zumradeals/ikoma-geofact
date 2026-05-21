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
     * flags=0x481 : 0x1=base + 0x80=champs admin + 0x400=position/lmsg.
     * Si lmsg reste null, vérifier les droits uacl du token Wialon (avl_unit_pos).
     *
     * @return array<int, array{id: int, name: string, last_pos: array|null}>
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
                    'flags'     => 0x481, // 0x1=base + 0x80=messages_params + 0x400=lmsg/pos
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

        // Log du premier item pour diagnostiquer les flags et droits d'accès
        if (! empty($items)) {
            $first    = $items[0];
            $lmsgVal  = $first['lmsg'] ?? null;
            Log::info('geofact.wialon.get_units.item_keys', [
                'keys'     => array_keys($first),
                'lmsg'     => $lmsgVal === null ? 'absent' : (is_array($lmsgVal) ? 'present' : 'null'),
                'has_pos'  => is_array($lmsgVal) && isset($lmsgVal['pos']),
                'uacl'     => $first['uacl'] ?? 'absent',
            ]);
        }

        $units = [];
        foreach ($items as $item) {
            $rawLmsg = $item['lmsg'] ?? null;
            $lmsg    = is_array($rawLmsg) ? $rawLmsg : null;
            $unitPos = $item['pos']  ?? null; // derniere position connue (flag 0x400)

            // Fallback : si lmsg n'a pas de pos, utiliser la derniere position connue.
            $unitPos = is_array($unitPos) ? $unitPos : null;

            if ($unitPos && (! $lmsg || ! isset($lmsg['pos']))) {
                $lmsg        = $lmsg ?? [];
                $lmsg['pos'] = $unitPos;
                $lmsg['t']   = $unitPos['t'] ?? ($lmsg['t'] ?? null);
            }

            $pos   = is_array($lmsg['pos'] ?? null) ? $lmsg['pos'] : null;
            $posTs = $pos['t'] ?? $lmsg['t'] ?? null;

            $units[] = [
                'id'      => (int) ($item['id'] ?? 0),
                'name'    => (string) ($item['nm'] ?? ''),
                'last_pos' => $pos ? [
                    'lat'   => $pos['y'] ?? null,
                    'lon'   => $pos['x'] ?? null,
                    'speed' => $pos['s'] ?? null,
                    'ts'    => $posTs,
                ] : null,
                'lmsg'    => $lmsg, // message brut pour le sync job
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

        Log::info('geofact.wialon.get_messages.raw', [
            'unit_id' => $unitId,
            'keys'    => array_keys($body ?? []),
            'error'   => $body['error'] ?? null,
            'count'   => $body['count'] ?? null,
            'from_ts' => $fromTs,
            'to_ts'   => $toTs,
        ]);

        // Wialon retourne {count: N, messages: [...]} ou une erreur {error: N}
        if (isset($body['error'])) {
            Log::warning('geofact.wialon.get_messages.api_error', [
                'unit_id'    => $unitId,
                'error_code' => $body['error'],
            ]);
            return [];
        }

        $messages = $body['messages'] ?? [];

        if (empty($messages)) {
            Log::info('geofact.wialon.get_messages.empty', [
                'unit_id' => $unitId,
                'from_ts' => $fromTs,
                'to_ts'   => $toTs,
            ]);
        }

        return $messages;
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
            'provider_unit_id' => (string) $unitId,
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

        // Correction : "i" est une entree digitale brute, pas un etat moteur fiable.
        $ignition = $this->extractIgnition($p);
        if ($ignition !== null) {
            $flat['ignition'] = $ignition;
        }

        return $flat;
    }

    private function extractIgnition(array $params): ?bool
    {
        $candidateKeys = [
            'ignition',
            'ign',
            'engine_ignition',
            'engine_on',
            'acc',
            'ACC',
        ];

        foreach ($candidateKeys as $key) {
            if (! array_key_exists($key, $params)) {
                continue;
            }

            $value = $params[$key];

            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (float) $value > 0;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if (in_array($normalized, ['1', 'true', 'on', 'yes', 'allume', 'allumé'], true)) {
                    return true;
                }

                if (in_array($normalized, ['0', 'false', 'off', 'no', 'eteint', 'éteint'], true)) {
                    return false;
                }
            }
        }

        return null;
    }
}
