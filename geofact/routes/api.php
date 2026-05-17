<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API V1 — IKOMA GEOFACT
|--------------------------------------------------------------------------
| Toutes les routes (sauf /auth/login et /auth/refresh) sont protégées par :
|  - jwt.auth        : valide le JWT + token_version + tolérance réseau
|  - tenant.inject   : injecte organization_id depuis le JWT (jamais du body)
|  - audit           : loggue toute action authentifiée dans audit_logs
|
| Le scope organizationId vient TOUJOURS de request()->tenant — jamais du body.
*/

Route::prefix('v1')->group(function () {

    // Auth — sans JWT requis pour le login initial
    Route::prefix('auth')->group(function () {
        Route::post('login',   [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['jwt.auth']);
        Route::post('logout',  [AuthController::class, 'logout'])->middleware(['jwt.auth']);
    });

    // Toutes les routes métier — protégées JWT + tenant + audit
    Route::middleware(['jwt.auth', 'tenant.inject', 'audit'])->group(function () {

        // Organizations
        Route::apiResource('organizations', \App\Http\Controllers\Api\V1\OrganizationController::class);

        // Fleets
        Route::apiResource('fleets', \App\Http\Controllers\Api\V1\FleetController::class);
        Route::patch('fleets/{id}/status', [\App\Http\Controllers\Api\V1\FleetController::class, 'updateStatus']);

        // Vehicles
        Route::apiResource('vehicles', \App\Http\Controllers\Api\V1\VehicleController::class);
        Route::patch('vehicles/{id}/status',       [\App\Http\Controllers\Api\V1\VehicleController::class, 'updateStatus']);
        Route::post('vehicles/{id}/transfer',      [\App\Http\Controllers\Api\V1\VehicleController::class, 'transfer']);
        Route::get('vehicles/{id}/active-trip',    [\App\Http\Controllers\Api\V1\TripController::class, 'activeTrip']);

        // Drivers
        Route::apiResource('drivers', \App\Http\Controllers\Api\V1\DriverController::class);

        // Trips
        Route::apiResource('trips', \App\Http\Controllers\Api\V1\TripController::class);

        // Alerts
        Route::apiResource('alerts', \App\Http\Controllers\Api\V1\AlertController::class);
        Route::patch('alerts/{id}/acknowledge', [\App\Http\Controllers\Api\V1\AlertController::class, 'acknowledge']);
        Route::patch('alerts/{id}/resolve',     [\App\Http\Controllers\Api\V1\AlertController::class, 'resolve']);

        // GeoZones
        Route::apiResource('geozones', \App\Http\Controllers\Api\V1\GeoZoneController::class);

        // KPIs
        Route::get('kpis/{scopeType}/{scopeId}',         [\App\Http\Controllers\Api\V1\KpiController::class, 'show']);
        Route::get('kpis/{scopeType}/{scopeId}/history', [\App\Http\Controllers\Api\V1\KpiController::class, 'history']);

        // Insights
        Route::get('insights/{scopeType}/{scopeId}',          [\App\Http\Controllers\Api\V1\InsightController::class, 'show']);
        Route::post('insights/{scopeType}/{scopeId}/generate', [\App\Http\Controllers\Api\V1\InsightController::class, 'generate']);
    });

    // Webhook Connector — authentifié par connector.auth (pas de JWT humain)
    Route::prefix('webhook')->middleware(['connector.auth'])->group(function () {
        Route::post('connector/ingest', [\App\Http\Controllers\Api\V1\ConnectorController::class, 'ingest']);
        Route::get('connector/pull/{id}', [\App\Http\Controllers\Api\V1\ConnectorController::class, 'pull']);
    });
});
