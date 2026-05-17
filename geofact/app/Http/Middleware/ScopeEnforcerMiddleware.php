<?php

namespace App\Http\Middleware;

use App\Auth\ScopeGuard;
use App\Exceptions\TenantViolationException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ScopeEnforcerMiddleware
{
    public function __construct(private readonly ScopeGuard $scopeGuard) {}

    /**
     * Vérifie le scope après injection du tenant.
     * Bloque toute tentative d'accès à une organization ou fleet hors scope JWT.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->get('tenant');
        /** @var User|null $user */
        $user   = auth()->user();

        if (! $tenant || ! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Contexte de sécurité incomplet.',
                'errors'  => ['scope' => 'Tenant ou utilisateur non résolu.'],
            ], 401);
        }

        try {
            // Vérifier le scope organization si présent dans la route
            $routeOrgId = $request->route('organization') ?? $request->route('organization_id');
            if ($routeOrgId && $routeOrgId !== $tenant['organization_id']) {
                $this->scopeGuard->checkOrganizationScope($user, $routeOrgId);
            }

            // Vérifier le scope fleet si présent dans la route
            $routeFleetId = $request->route('fleet') ?? $request->route('fleet_id');
            if ($routeFleetId) {
                $this->scopeGuard->checkFleetScope($user, $routeFleetId);
            }

            return $next($request);

        } catch (TenantViolationException $e) {
            Log::warning('geofact.scope.access_denied', [
                'user_id' => $user->id,
                'path'    => $request->path(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Accès refusé — hors scope.',
                'errors'  => ['scope' => 'Ressource hors de votre périmètre.'],
            ], 403);
        }
    }
}
