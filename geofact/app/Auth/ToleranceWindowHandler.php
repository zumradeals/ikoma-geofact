<?php

namespace App\Auth;

use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class ToleranceWindowHandler
{
    private int $toleranceHours;

    public function __construct()
    {
        $this->toleranceHours = (int) config('geofact.tolerance_window_hours', 4);
    }

    /**
     * Tente de renouveler silencieusement un token expiré.
     *
     * Règles C-12.3 :
     * - Token expiré < tolerance_window  → renouvellement silencieux, nouveau token retourné
     * - Token expiré > tolerance_window  → null (ré-auth obligatoire)
     * - Token révoqué                    → null (rejet immédiat, tolérance non applicable)
     */
    public function handle(string $token): ?string
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $expiredAt = $payload->get('exp');
            $now = now()->timestamp;

            $expiredSinceSeconds = $now - $expiredAt;
            $toleranceSeconds    = $this->toleranceHours * 3600;

            if ($expiredSinceSeconds <= $toleranceSeconds) {
                $newToken = JWTAuth::setToken($token)->refresh();
                Log::info('geofact.auth.token_renewed_silently', [
                    'expired_since_minutes' => round($expiredSinceSeconds / 60, 1),
                ]);
                return $newToken;
            }

            Log::info('geofact.auth.token_expired_beyond_tolerance', [
                'expired_since_hours' => round($expiredSinceSeconds / 3600, 2),
                'tolerance_hours'     => $this->toleranceHours,
            ]);
            return null;

        } catch (TokenExpiredException $e) {
            // Token expiré mais le refresh a échoué (révoqué)
            Log::warning('geofact.auth.token_revoked_no_tolerance');
            return null;
        } catch (JWTException $e) {
            Log::warning('geofact.auth.tolerance_handler_error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Vérifie si un token révoqué doit être rejeté immédiatement sans tolérance.
     */
    public function isRevoked(string $token): bool
    {
        try {
            // JWTAuth lève TokenBlacklistedException si révoqué
            JWTAuth::setToken($token)->checkOrFail();
            return false;
        } catch (JWTException) {
            return true;
        }
    }
}
