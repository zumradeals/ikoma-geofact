<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtService
{
    /**
     * Génère un JWT GEOFACT complet (C-12.2).
     * Les custom claims sont définis dans User::getJWTCustomClaims().
     */
    public function generateToken(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    /**
     * Valide la signature, l'expiration et la token_version.
     * Retourne l'utilisateur ou null si invalide.
     */
    public function validateToken(string $token): ?User
    {
        try {
            $user = JWTAuth::setToken($token)->authenticate();

            if (! $user instanceof User) {
                return null;
            }

            // Déléguer la vérification de version à TokenVersionGuard
            $guard = app(TokenVersionGuard::class);
            if (! $guard->check($token, $user)) {
                return null;
            }

            return $user;
        } catch (TokenExpiredException $e) {
            // Laisser ToleranceWindowHandler décider
            return null;
        } catch (TokenInvalidException | JWTException $e) {
            Log::warning('geofact.jwt.invalid', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Renouvelle le token si dans la fenêtre de tolérance (C-12.3).
     * Retourne le nouveau token ou null si hors tolérance / révoqué.
     */
    public function refreshToken(string $token): ?string
    {
        try {
            $newToken = JWTAuth::setToken($token)->refresh();
            Log::info('geofact.jwt.refreshed_silently');
            return $newToken;
        } catch (JWTException $e) {
            Log::warning('geofact.jwt.refresh_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Invalide le token courant (logout).
     */
    public function invalidateToken(string $token): void
    {
        try {
            JWTAuth::setToken($token)->invalidate();
        } catch (JWTException $e) {
            Log::warning('geofact.jwt.invalidate_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Extrait le payload brut sans validation complète.
     */
    public function getPayload(string $token): ?array
    {
        try {
            return JWTAuth::setToken($token)->getPayload()->toArray();
        } catch (JWTException) {
            return null;
        }
    }
}
