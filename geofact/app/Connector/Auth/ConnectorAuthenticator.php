<?php

namespace App\Connector\Auth;

use App\Exceptions\ConnectorAuthException;
use App\Models\Connector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class ConnectorAuthenticator
{
    /**
     * Authentifie un Connector entrant.
     * Contrat C-10 : si invalide → rien n'est écrit, retourne 401 immédiatement.
     *
     * Vérifie dans l'ordre strict :
     * 1. Token Bearer présent
     * 2. JWT décodable + sub = connector_id
     * 3. Connector trouvé en base
     * 4. status = active (revoked → rejet définitif)
     * 5. token_hash bcrypt correspond
     * 6. token_version JWT >= token_version DB
     *
     * @throws ConnectorAuthException si une condition échoue
     */
    public function authenticate(Request $request): Connector
    {
        $token = $this->extractToken($request);

        if (! $token) {
            throw new ConnectorAuthException('Token Bearer absent — authentification Connector impossible.');
        }

        try {
            $payload      = JWTAuth::setToken($token)->getPayload();
            $connectorId  = $payload->get('sub');
            $tokenVersion = (int) $payload->get('token_version', 0);

            if (! $connectorId) {
                throw new ConnectorAuthException('sub (connector_id) absent du token JWT.');
            }

        } catch (JWTException $e) {
            Log::warning('geofact.connector.auth.jwt_decode_failed', ['error' => $e->getMessage()]);
            throw new ConnectorAuthException("Token JWT Connector invalide : {$e->getMessage()}");
        }

        $connector = Connector::find($connectorId);

        if (! $connector) {
            Log::warning('geofact.connector.auth.unknown', ['connector_id' => $connectorId]);
            throw new ConnectorAuthException("Connector [{$connectorId}] inconnu.");
        }

        if ($connector->status === 'revoked') {
            Log::warning('geofact.connector.auth.revoked', ['connector_id' => $connectorId]);
            throw new ConnectorAuthException("Connector [{$connectorId}] révoqué définitivement.");
        }

        if ($connector->status !== 'active') {
            Log::warning('geofact.connector.auth.not_active', [
                'connector_id' => $connectorId,
                'status'       => $connector->status,
            ]);
            throw new ConnectorAuthException("Connector [{$connectorId}] non actif : {$connector->status}.");
        }

        if (! Hash::check($token, $connector->token_hash)) {
            Log::warning('geofact.connector.auth.token_mismatch', ['connector_id' => $connectorId]);
            throw new ConnectorAuthException("Token Connector invalide — hash bcrypt incorrect.");
        }

        if ($tokenVersion < $connector->token_version) {
            Log::warning('geofact.connector.auth.version_revoked', [
                'connector_id'  => $connectorId,
                'token_version' => $tokenVersion,
                'db_version'    => $connector->token_version,
            ]);
            throw new ConnectorAuthException("Token révoqué — token_version {$tokenVersion} < {$connector->token_version}.");
        }

        return $connector;
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
