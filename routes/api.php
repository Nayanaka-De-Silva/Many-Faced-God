<?php

use App\Http\Controllers\Api\V1\DiscoveryController;
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
*/

Route::prefix('v1')
    ->middleware(['throttle:300,1', AddApiVersionHeader::class])
    ->group(function (): void {
        Route::get('/', [DiscoveryController::class, 'root']);
        Route::get('/health', [DiscoveryController::class, 'health']);

        // metadata() and openapi() are reserved for a later batch — do not
        // register placeholder routes here (they would 404 misleadingly).
    });
