<?php

namespace App\Facades;

use App\Auth\RolePermissionMatrix;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool can(string $role, string $action)
 * @method static array allowedActions(string $role)
 */
class GeofactPermissions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RolePermissionMatrix::class;
    }
}
