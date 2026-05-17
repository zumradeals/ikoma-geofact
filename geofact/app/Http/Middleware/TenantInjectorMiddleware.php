<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class TenantInjectorMiddleware
{
    /**
     * Injecte organization_id dans le contexte de la requête.
     *
     * Contrat absolu (C-12) :
     * - Le scope organization_id vient TOUJOURS du JWT validé
     * - Jamais du body de la requête
     * - Jamais des query params
     * - Jamais d'un header client
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();

            $organizationId = $payload->get('organization_id');
            $fleetIds       = $payload->get('fleet_ids', []);
            $role           = $payload->get('role');
            $actorType      = $payload->get('actor_type', 'human');
            $tokenVersion   = $payload->get('token_version');

            if (! $organizationId) {
                Log::error('geofact.tenant.organization_id_missing_from_jwt');
                return response()->json([
                    'success' => false,
                    'message' => 'Contexte tenant invalide.',
                    'errors'  => ['tenant' => 'organization_id absent du token.'],
                ], 401);
            }

            // Objet tenant injecté — accessible via request()->tenant dans tous les Controllers
            $request->merge([
                'tenant' => [
                    'organization_id' => $organizationId,
                    'fleet_ids'       => $fleetIds,
                    'role'            => $role,
                    'actor_type'      => $actorType,
                    'token_version'   => $tokenVersion,
                ],
            ]);

            return $next($request);

        } catch (JWTException $e) {
            Log::warning('geofact.tenant.jwt_parse_failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Token requis pour injecter le contexte tenant.',
                'errors'  => ['tenant' => $e->getMessage()],
            ], 401);
        }
    }
}
