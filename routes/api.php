<?php

use App\Http\Controllers\Api\V1\DiscoveryController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\NpcController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Middleware\AddApiVersionHeader;
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
| AddApiVersionHeader stamps X-MFG-API-Version: 1 on matched route responses.
| Unmatched-route error responses (404, 405) receive the header from the
| exception render hook in bootstrap/app.php.
|
| Route params for {npc}, {template}, {folder} use int type-hinted controller
| args rather than implicit route model binding, so each controller can scope
| the query correctly (npcs scope excludes templates, templates scope excludes
| npcs). Implicit binding alone would not apply those scopes.
|
*/

Route::prefix('v1')
    ->middleware(['throttle:300,1', AddApiVersionHeader::class])
    ->group(function (): void {
        Route::get('/', [DiscoveryController::class, 'root']);
        Route::get('/health', [DiscoveryController::class, 'health']);

        // NPCs — non-template statblocks only (is_template = false)
        Route::get('/npcs', [NpcController::class, 'index']);
        Route::get('/npcs/{npc}', [NpcController::class, 'show']);

        // Templates — template statblocks only (is_template = true)
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/templates/{template}', [TemplateController::class, 'show']);

        // Folders — tree and individual folder detail
        Route::get('/folders', [FolderController::class, 'index']);
        Route::get('/folders/{folder}', [FolderController::class, 'show']);

        // metadata() and openapi() are reserved for a later batch — do not
        // register placeholder routes here (they would 404 misleadingly).
    });
