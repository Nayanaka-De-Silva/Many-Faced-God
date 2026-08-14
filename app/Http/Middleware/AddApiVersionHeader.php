<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps X-MFG-API-Version: 1 on every matched api/v1/* response.
 *
 * Unmatched routes (404, 405) do not reach this middleware because route-group
 * middleware only runs when a route is dispatched. Those paths are covered by
 * the exception render hook in bootstrap/app.php instead.
 *
 * NOTE: The SetApiCacheHeaders middleware (step 8) will likely absorb this
 * responsibility; keep logic minimal here so consolidation is straightforward.
 */
class AddApiVersionHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request)->header('X-MFG-API-Version', '1');
    }
}
