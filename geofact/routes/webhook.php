<?php

use App\Http\Controllers\Api\V1\ConnectorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Connector Webhook — IKOMA GEOFACT
|--------------------------------------------------------------------------
| Protégées par connector.auth uniquement (pas de JWT humain).
| Le ConnectorAuthMiddleware valide : connector_id, token_hash bcrypt, token_version, status=active.
*/

Route::middleware(['connector.auth'])->group(function () {
    Route::post('connector/ingest',       [ConnectorController::class, 'ingest']);
    Route::get('connector/pull/{id}',     [ConnectorController::class, 'pull']);
});
