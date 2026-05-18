<?php

namespace App\Auth;

class RolePermissionMatrix
{
    /**
     * Matrice des droits GEOFACT — C-12.5 / C-08.5.
     * Rôles fermés en v1 — aucune modification sans révision contractuelle.
     *
     * Format : action => [rôles autorisés]
     */
    private const MATRIX = [
        'create_organization' => [
            'geofact_admin',
        ],
        'create_fleet' => [
            'geofact_admin',
            'org_admin',
        ],
        'create_vehicle' => [
            'geofact_admin',
            'org_admin',
            'fleet_admin',
        ],
        'create_driver' => [
            'geofact_admin',
            'org_admin',
            'fleet_admin',
        ],
        'register_connector' => [
            'geofact_admin',
            'integrator',
        ],
        'configure_ccm' => [
            'geofact_admin',
            'integrator',
        ],
        'adjust_thresholds' => [
            'geofact_admin',
            'org_admin',
            'integrator',
        ],
        'create_rmc' => [
            'geofact_admin',
            'integrator',
        ],
        'initiate_transfer' => [
            'geofact_admin',
            'org_admin',
        ],
        'validate_transfer' => [
            'geofact_admin',
        ],
        'physical_delete' => [
            'geofact_admin',
        ],
        'view_organization' => [
            'geofact_admin',
            'org_admin',
            'integrator',
            'fleet_admin',
            'supervisor',
        ],
        'view_fleet' => [
            'geofact_admin',
            'org_admin',
            'integrator',
            'fleet_admin',
            'supervisor',
        ],
        'view_vehicle' => [
            'geofact_admin',
            'org_admin',
            'integrator',
            'fleet_admin',
            'supervisor',
            'driver',
        ],
        'view_kpi' => [
            'geofact_admin',
            'org_admin',
            'integrator',
            'fleet_admin',
            'supervisor',
        ],
        'view_alert' => [
            'geofact_admin',
            'org_admin',
            'integrator',
            'fleet_admin',
            'supervisor',
        ],
        'acknowledge_alert' => [
            'geofact_admin',
            'org_admin',
            'fleet_admin',
            'supervisor',
        ],
        'resolve_alert' => [
            'geofact_admin',
            'org_admin',
            'fleet_admin',
            'supervisor',
        ],
        'manage_geozone' => [
            'geofact_admin',
            'org_admin',
            'integrator',
        ],
        'generate_insight' => [
            'geofact_admin',
            'org_admin',
            'fleet_admin',
        ],
    ];

    /**
     * Alias of canPerform — used by GeofactPermissions facade.
     */
    public function can(string $role, string $action): bool
    {
        return $this->canPerform($role, $action);
    }

    /**
     * Vérifie si un rôle peut exécuter une action donnée.
     */
    public function canPerform(string $role, string $action): bool
    {
        $allowed = self::MATRIX[$action] ?? [];
        return in_array($role, $allowed, true);
    }

    /**
     * Retourne toutes les actions autorisées pour un rôle.
     */
    public function allowedActions(string $role): array
    {
        return array_keys(array_filter(
            self::MATRIX,
            fn(array $roles) => in_array($role, $roles, true)
        ));
    }
}
