<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    /**
     * Loggue toute action authentifiée en fin de requête.
     * Contrat C-12 : toute action authentifiée génère un INSERT dans audit_logs.
     *
     * Ne bloque jamais la réponse — erreur d'audit loggée mais non propagée.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Audit en terminaison — après que la réponse soit produite
        $this->writeAuditLog($request, $response);

        return $response;
    }

    private function writeAuditLog(Request $request, Response $response): void
    {
        try {
            /** @var User|null $user */
            $user   = auth()->user();
            $tenant = $request->get('tenant', []);

            // Pas d'audit sans acteur identifié
            if (! $user && empty($tenant)) {
                return;
            }

            $statusCode = $response->getStatusCode();
            $result     = match(true) {
                $statusCode >= 200 && $statusCode < 300 => 'success',
                $statusCode === 403                      => 'forbidden',
                default                                  => 'rejected',
            };

            // N'audite pas les lectures simples (GET) sans impact — uniquement les mutations
            // et les accès refusés (toujours audités)
            if ($request->isMethod('GET') && $result === 'success') {
                return;
            }

            $actorId   = $user?->id ?? ($tenant['sub'] ?? 'system');
            $actorRole = $user?->role ?? ($tenant['role'] ?? 'system');
            $orgId     = $user?->organization_id ?? ($tenant['organization_id'] ?? null);

            AuditLog::create([
                'id'              => Str::uuid()->toString(),
                'actor_id'        => $actorId,
                'actor_role'      => $actorRole,
                'organization_id' => $orgId,
                'action'          => $request->method() . ':' . $request->path(),
                'resource_type'   => $this->guessResourceType($request),
                'resource_id'     => $this->guessResourceId($request),
                'result'          => $result,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'payload'         => [
                    'method'      => $request->method(),
                    'path'        => $request->path(),
                    'status_code' => $statusCode,
                ],
                'created_at'      => now(),
            ]);

        } catch (\Throwable $e) {
            // Erreur d'audit jamais silencieuse — loggée mais ne bloque pas
            Log::error('geofact.audit.write_failed', [
                'error'  => $e->getMessage(),
                'path'   => $request->path(),
                'method' => $request->method(),
            ]);
        }
    }

    private function guessResourceType(Request $request): string
    {
        $segments = $request->segments();
        // /api/v1/{resource}/... → segments[2] est le type de ressource
        return $segments[2] ?? $segments[1] ?? 'unknown';
    }

    private function guessResourceId(Request $request): ?string
    {
        $segments = $request->segments();
        // /api/v1/{resource}/{id} → segments[3]
        $candidate = $segments[3] ?? null;

        // Vérifie que c'est un UUID ou un identifiant valide
        if ($candidate && Str::isUuid($candidate)) {
            return $candidate;
        }

        return null;
    }
}
