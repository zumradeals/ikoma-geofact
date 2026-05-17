<?php

use App\Http\Middleware\AuditMiddleware;
use App\Http\Middleware\ConnectorAuthMiddleware;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\ScopeEnforcerMiddleware;
use App\Http\Middleware\TenantInjectorMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aliases GEOFACT — utilisés dans routes/api.php et routes/webhook.php
        $middleware->alias([
            'jwt.auth'       => JwtAuthMiddleware::class,
            'tenant.inject'  => TenantInjectorMiddleware::class,
            'scope.enforce'  => ScopeEnforcerMiddleware::class,
            'connector.auth' => ConnectorAuthMiddleware::class,
            'audit'          => AuditMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
