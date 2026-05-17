<?php

namespace App\Auth;

use App\Exceptions\TenantViolationException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScopeGuard
{
    /**
     * Vérifie que l'utilisateur appartient à l'organization demandée.
     * geofact_admin : accès total à toutes les organizations.
     */
    public function checkOrganizationScope(User $user, string $organizationId): void
    {
        if ($user->role === 'geofact_admin') {
            return;
        }

        if ($user->organization_id !== $organizationId) {
            $this->denyAndLog($user, 'organization', $organizationId, 'organization_scope_violation');
            throw new TenantViolationException(
                "Accès refusé : organization [{$organizationId}] hors scope."
            );
        }
    }

    /**
     * Vérifie que l'utilisateur a accès à la fleet demandée.
     * - geofact_admin / org_admin : accès total aux fleets de leur org
     * - fleet_admin / supervisor  : uniquement leurs fleet_ids déclarés
     */
    public function checkFleetScope(User $user, string $fleetId): void
    {
        if (in_array($user->role, ['geofact_admin', 'org_admin', 'integrator'])) {
            return;
        }

        $allowedFleets = $user->fleet_ids ?? [];

        if (! in_array($fleetId, $allowedFleets)) {
            $this->denyAndLog($user, 'fleet', $fleetId, 'fleet_scope_violation');
            throw new TenantViolationException(
                "Accès refusé : fleet [{$fleetId}] hors scope."
            );
        }
    }

    private function denyAndLog(User $user, string $resourceType, string $resourceId, string $action): void
    {
        Log::warning("geofact.security.{$action}", [
            'actor_id'    => $user->id,
            'role'        => $user->role,
            'resource'    => "{$resourceType}:{$resourceId}",
            'org'         => $user->organization_id,
        ]);

        try {
            AuditLog::create([
                'id'              => Str::uuid()->toString(),
                'actor_id'        => $user->id,
                'actor_role'      => $user->role,
                'organization_id' => $user->organization_id,
                'action'          => $action,
                'resource_type'   => $resourceType,
                'resource_id'     => $resourceId,
                'result'          => 'forbidden',
                'ip_address'      => request()->ip(),
                'user_agent'      => request()->userAgent(),
                'payload'         => [
                    'attempted_resource' => "{$resourceType}:{$resourceId}",
                    'user_org'           => $user->organization_id,
                ],
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('geofact.audit.write_failed', ['error' => $e->getMessage()]);
        }
    }
}
