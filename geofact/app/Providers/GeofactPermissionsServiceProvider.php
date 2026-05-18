<?php

namespace App\Providers;

use App\Auth\RolePermissionMatrix;
use Illuminate\Support\ServiceProvider;

class GeofactPermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RolePermissionMatrix::class, fn() => new RolePermissionMatrix());
    }
}
