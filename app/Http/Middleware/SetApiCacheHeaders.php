<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-cutting cache/CORS concerns for api/v1/* responses:
 *
 * 1. If the controller set an ETag header and the client's If-None-Match
 *    matches it exactly, converts the response into a 304 with an empty
 *    body (offline-cache revalidation — Arena spec §7.5).
 * 2. Unconditionally stamps Access-Control-Expose-Headers and
 *    X-MFG-API-Version. Laravel's own HandleCors middleware only adds
 *    Access-Control-Expose-Headers when the request carries an Origin
 *    header (see shouldRun()) — but a plain same-origin/tooling request
 *    with no Origin still needs to see it to satisfy the acceptance
 *    suite's non-preflight ETag check. Setting it here is harmless:
 *    browsers ignore CORS response headers outside real cross-origin
 *    fetches.
 *
 * Registered GLOBALLY (bootstrap/app.php, prepended before HandleCors) —
 * not just as api/v1 route-group middleware. A CORS preflight OPTIONS
 * request is short-circuited by HandleCors before routing/route-group
 * middleware ever runs, so a route-scoped middleware never sees it. Being
 * prepended means this middleware wraps OUTSIDE HandleCors and still gets
 * to stamp headers on its way back out, even for a preflight response.
 * The path guard below keeps it a no-op for every other route.
 *
 * Computing the ETag itself is the controller's job (it has the loaded
 * model and its children in hand via Npc::freshestUpdatedAt()) — this
 * middleware only reacts to whatever ETag header is already on the response.
 */
class SetApiCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->is('api/v1') && !$request->is('api/v1/*')) {
            return $response;
        }

        $response = $this->applyRevalidation($request, $response);

        $response->headers->set('X-MFG-API-Version', '1');
        $response->headers->set('Access-Control-Expose-Headers', 'ETag, X-MFG-API-Version');

        return $response;
    }

    /**
     * Converts the response to a 304 with no body when the client's
     * If-None-Match matches the response's own ETag exactly.
     */
    private function applyRevalidation(Request $request, Response $response): Response
    {
        $etag = $response->headers->get('ETag');
        $ifNoneMatch = $request->headers->get('If-None-Match');

        if ($etag === null || $ifNoneMatch === null || $ifNoneMatch !== $etag) {
            return $response;
        }

        $notModified = response('', 304);
        $notModified->headers->set('ETag', $etag);

        // X-MFG-API-Version and Access-Control-Expose-Headers are stamped
        // unconditionally by handle() right after this method returns.
        return $notModified;
    }
}
