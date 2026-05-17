<?php

namespace App\Http\Middleware;

use App\Exceptions\ConnectorAuthException;
use App\Models\Connector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class ConnectorAuthMiddleware
{
    /**
     * Authentifie un Connector sur les routes webhook.
     *
     * Vérifie dans l'ordre :
     * 1. connector_id présent dans le payload JWT
     * 2. Connector existe et status = active
     * 3. token_hash bcrypt correspond au token présenté
     * 4. token_version du JWT >= token_version en base
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $this->extractToken($request);

            if (! $token) {
                return $this->reject('Token connector manquant.');
            }

            $payload     = JWTAuth::setToken($token)->getPayload();
            $connectorId = $payload->get('sub');
            $tokenVersion = (int) $payload->get('token_version', 0);

            if (! $connectorId) {
                return $this->reject('Identifiant connector absent du token.');
            }

            $connector = Connector::find($connectorId);

            if (! $connector) {
                Log::warning('geofact.connector.auth.not_found', ['connector_id' => $connectorId]);
                return $this->reject('Connector inconnu.');
            }

            if ($connector->status !== 'active') {
                Log::warning('geofact.connector.auth.not_active', [
                    'connector_id' => $connectorId,
                    'status'       => $connector->status,
                ]);
                return $this->reject("Connector {$connector->status} — accès refusé.");
            }

            // Vérification token_hash (bcrypt)
            if (! Hash::check($token, $connector->token_hash)) {
                Log::warning('geofact.connector.auth.token_hash_mismatch', ['connector_id' => $connectorId]);
                return $this->reject('Token connector invalide.');
            }

            // Vérification token_version — toute version inférieure rejetée
            if ($tokenVersion < $connector->token_version) {
                Log::warning('geofact.connector.auth.token_version_rejected', [
                    'connector_id'  => $connectorId,
                    'token_version' => $tokenVersion,
                    'db_version'    => $connector->token_version,
                ]);
                return $this->reject('Token connector révoqué — version expirée.');
            }

            // Injecter le connector dans la requête
            $request->merge(['_connector' => $connector]);

            return $next($request);

        } catch (JWTException $e) {
            Log::warning('geofact.connector.auth.jwt_exception', ['error' => $e->getMessage()]);
            return $this->reject('Token connector invalide.');
        }
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    private function reject(string $reason): Response
    {
        return response()->json([
            'success' => false,
            'message' => $reason,
            'errors'  => ['connector_auth' => $reason],
        ], 401);
    }
}
