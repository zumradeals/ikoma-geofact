<?php

namespace App\Http\Middleware;

use App\Auth\JwtService;
use App\Auth\ToleranceWindowHandler;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthMiddleware
{
    public function __construct(
        private readonly JwtService             $jwtService,
        private readonly ToleranceWindowHandler $toleranceHandler,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token manquant.',
                'errors'  => ['auth' => 'Authorization header requis.'],
            ], 401);
        }

        try {
            $user = JWTAuth::setToken($token)->authenticate();

            if (! $user) {
                return $this->unauthorized('Utilisateur introuvable.');
            }

            // Vérification token_version (révocation par rotation)
            if ((int) JWTAuth::getPayload()->get('token_version') < $user->token_version) {
                Log::warning('geofact.auth.token_version_revoked', ['user_id' => $user->id]);
                return $this->unauthorized('Token révoqué.');
            }

            // Injecter l'utilisateur authentifié dans la requête
            $request->merge(['_authenticated_user' => $user]);
            auth()->setUser($user);

            return $next($request);

        } catch (TokenExpiredException) {
            // Tentative de renouvellement silencieux (C-12.3)
            $newToken = $this->toleranceHandler->handle($token);

            if (! $newToken) {
                return $this->unauthorized('Token expiré — ré-authentification requise.');
            }

            // Renouvellement réussi : on continue avec le nouveau token
            try {
                $user = JWTAuth::setToken($newToken)->authenticate();
                $request->merge(['_authenticated_user' => $user]);
                auth()->setUser($user);

                $response = $next($request);
                $response->headers->set('X-New-Token', $newToken);
                return $response;
            } catch (JWTException $e) {
                Log::error('geofact.auth.post_refresh_auth_failed', ['error' => $e->getMessage()]);
                return $this->unauthorized('Erreur d\'authentification après renouvellement.');
            }

        } catch (JWTException $e) {
            Log::warning('geofact.auth.jwt_exception', ['error' => $e->getMessage()]);
            return $this->unauthorized('Token invalide.');
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

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => ['auth' => $message],
        ], 401);
    }
}
