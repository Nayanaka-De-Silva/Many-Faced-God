<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only discovery endpoints for the MFG public API v1.
 */
class DiscoveryController extends Controller
{
    /**
     * GET /api/v1/
     *
     * Returns the full endpoint contract so clients (e.g. Arena) can probe the
     * available surface without hard-coding paths. Lists all planned endpoints
     * even if they are not yet implemented, giving the caller the full contract.
     *
     * NOTE: the top-level spec-link key is "openapi" (matching the executable
     * Postman contract in docs/api/postman/), not "openApiSpec" — do not rename.
     */
    public function root(Request $request): JsonResponse
    {
        $base = '/api/v1';

        return response()->json([
            'version'   => 1,
            'endpoints' => [
                'root'      => "{$base}/",
                'health'    => "{$base}/health",
                'metadata'  => "{$base}/metadata",
                'openapi'   => "{$base}/openapi.yaml",
                'npcs'      => "{$base}/npcs",
                'npc'       => "{$base}/npcs/{id}",
                'templates' => "{$base}/templates",
                'template'  => "{$base}/templates/{id}",
                'folders'   => "{$base}/folders",
                'folder'    => "{$base}/folders/{id}",
            ],
            'openapi' => "{$base}/openapi.yaml",
        ]);
    }

    /**
     * GET /api/v1/health
     *
     * Minimal liveness probe — mirrors the SpellLibraryClient /health contract.
     */
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
