<?php

use App\Http\Controllers\Api\V1\DiscoveryController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\NpcController;
use App\Http\Controllers\Api\V1\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
|
| Throttle uses the numeric form (throttle:300,1) intentionally.
| Do NOT switch to throttleApi() or a named limiter — no RateLimiter::for()
| definition exists in AppServiceProvider, and a named throttle with no
| registered limiter will 500.
|
| X-MFG-API-Version and cache/CORS headers are stamped by SetApiCacheHeaders,
| registered GLOBALLY in bootstrap/app.php (not here) — a route-group
| middleware never runs for a CORS preflight OPTIONS request, since
| HandleCors short-circuits it before routing. See that middleware's
| docblock for why it must be global.
|
| Route params for {npc}, {template}, {folder} use int type-hinted controller
| args rather than implicit route model binding, so each controller can scope
| the query correctly (npcs scope excludes templates, templates scope excludes
| npcs). Implicit binding alone would not apply those scopes.
|
| Each id param is constrained to digits via where(). Without this, a
| non-numeric segment (e.g. GET /npcs/abc) reaches the controller and PHP's
| weak-mode int coercion throws an uncaught TypeError on a non-numeric
| string, yielding a 500 instead of the documented 404. Constraining the
| route makes a non-numeric segment simply not match, falling through to
| the same NotFoundHttpException path as any other unmatched route.
|
*/

Route::prefix('v1')
    ->middleware(['throttle:300,1'])
    ->group(function (): void {
        Route::get('/', [DiscoveryController::class, 'root']);
        Route::get('/health', [DiscoveryController::class, 'health']);
        Route::get('/metadata', [DiscoveryController::class, 'metadata']);
        Route::get('/openapi.yaml', [DiscoveryController::class, 'openapi']);

        // NPCs — non-template statblocks only (is_template = false)
        Route::get('/npcs', [NpcController::class, 'index']);
        Route::get('/npcs/{npc}', [NpcController::class, 'show'])->where('npc', '[0-9]+');

        // Templates — template statblocks only (is_template = true)
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/templates/{template}', [TemplateController::class, 'show'])->where('template', '[0-9]+');

        // Folders — tree and individual folder detail
        Route::get('/folders', [FolderController::class, 'index']);
        Route::get('/folders/{folder}', [FolderController::class, 'show'])->where('folder', '[0-9]+');
    });
