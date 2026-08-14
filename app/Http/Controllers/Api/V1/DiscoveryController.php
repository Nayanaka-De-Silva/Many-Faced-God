<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Npc;
use App\Models\NpcAction;
use App\Models\NpcCastingProfile;
use App\Support\ChallengeRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

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

    /**
     * GET /api/v1/metadata
     *
     * Static vocabularies used across NPC statblocks. Cached 24h, mirroring
     * SpellLibraryClient::metadata()'s pattern. No data/meta envelope — this
     * is a fixed dump, not a paginated resource (same treatment as /health).
     */
    public function metadata(): JsonResponse
    {
        $metadata = Cache::remember('api.v1.metadata', now()->addDay(), function (): array {
            return [
                'alignments'      => Npc::ALIGNMENTS,
                'skills'          => array_keys(Npc::SKILLS),
                'damageTypes'     => Npc::DAMAGE_TYPES,
                'conditions'      => Npc::CONDITIONS,
                'senseCategories' => Npc::SENSE_CATEGORIES,
                'actionTypes'     => NpcAction::ACTION_TYPES,
                'castingTypes'    => NpcCastingProfile::CASTING_TYPES,
                'challengeRatings' => $this->challengeRatingVocabulary(),
            ];
        });

        return response()->json($metadata);
    }

    /**
     * GET /api/v1/openapi.yaml
     *
     * Serves the hand-written OpenAPI 3.1 contract as a static file.
     */
    public function openapi(): Response
    {
        $path = base_path('openapi/v1.yaml');

        return response(file_get_contents($path), 200, ['Content-Type' => 'text/yaml']);
    }

    /**
     * Builds the full challengeRatings vocabulary from ChallengeRating::ORDERED
     * so it is never hand-duplicated a third time.
     *
     * @return array<string, array{xp: int|null, xpIfDangerous: int|null}>
     */
    private function challengeRatingVocabulary(): array
    {
        $vocabulary = [];

        foreach (ChallengeRating::ORDERED as $cr) {
            $vocabulary[$cr] = [
                'xp'            => ChallengeRating::xp($cr),
                'xpIfDangerous' => $cr === '0' ? ChallengeRating::XP_CR_ZERO_IF_DANGEROUS : null,
            ];
        }

        return $vocabulary;
    }
}
