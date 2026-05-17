<?php

namespace App\Http\Middleware;

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
     * Deux modes :
     * - X-Connector-Token header : token brut vérifié via bcrypt contre token_hash
     * - Authorization: Bearer <jwt> : JWT avec sub=connector_id + token_version
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Mode 1 : X-Connector-Token (devices GPS, tokens pré-partagés)
        $rawToken = $request->header('X-Connector-Token');
        if ($rawToken) {
            return $this->authenticateRawToken($rawToken, $request, $next);
        }

        // Mode 2 : Authorization: Bearer <jwt>
        $bearerToken = $this->extractBearer($request);
        if ($bearerToken) {
            return $this->authenticateJwt($bearerToken, $request, $next);
        }

        return $this->reject('Token connector manquant.');
    }

    private function authenticateRawToken(string $token, Request $request, Closure $next): Response
    {
        // Cherche un connector actif dont le token_hash correspond
        $connector = Connector::where('status', 'active')
            ->get()
            ->first(fn(Connector $c) => Hash::check($token, $c->token_hash));

        if (! $connector) {
            Log::warning('geofact.connector.auth.raw_token_invalid');
            return $this->reject('Token connector invalide.');
        }

        $request->merge(['_connector' => $connector]);
        return $next($request);
    }

    private function authenticateJwt(string $token, Request $request, Closure $next): Response
    {
        try {
            $payload      = JWTAuth::setToken($token)->getPayload();
            $connectorId  = $payload->get('sub');
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

            if (! Hash::check($token, $connector->token_hash)) {
                Log::warning('geofact.connector.auth.token_hash_mismatch', ['connector_id' => $connectorId]);
                return $this->reject('Token connector invalide.');
            }

            if ($tokenVersion < $connector->token_version) {
                Log::warning('geofact.connector.auth.token_version_rejected', [
                    'connector_id'  => $connectorId,
                    'token_version' => $tokenVersion,
                    'db_version'    => $connector->token_version,
                ]);
                return $this->reject('Token connector révoqué — version expirée.');
            }

            $request->merge(['_connector' => $connector]);
            return $next($request);

        } catch (JWTException $e) {
            Log::warning('geofact.connector.auth.jwt_exception', ['error' => $e->getMessage()]);
            return $this->reject('Token connector invalide.');
        }
    }

    private function extractBearer(Request $request): ?string
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
