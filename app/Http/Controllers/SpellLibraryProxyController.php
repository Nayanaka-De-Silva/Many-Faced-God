<?php

namespace App\Http\Controllers;

use App\Exceptions\SpellLibraryUnavailableException;
use App\Services\SpellLibrary\SpellLibraryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only proxy — the browser talks to these endpoints, never directly to the library.
 */
class SpellLibraryProxyController extends Controller
{
    public function __construct(private readonly SpellLibraryClient $client)
    {
    }

    /** GET /api/spell-library/spells */
    public function index(Request $request): JsonResponse
    {
        try {
            $params = $request->only(SpellLibraryClient::ALLOWED_SEARCH_PARAMS);
            return response()->json($this->client->searchSpells($params));
        } catch (SpellLibraryUnavailableException) {
            return $this->libraryUnavailableResponse();
        }
    }

    /** GET /api/spell-library/spells/{id} */
    public function show(string $id): JsonResponse
    {
        try {
            $spell = $this->client->getSpell($id);

            if (is_null($spell)) {
                return response()->json(
                    ['error' => ['code' => 'NOT_FOUND', 'message' => 'Spell not found.']],
                    404
                );
            }

            return response()->json($spell);
        } catch (SpellLibraryUnavailableException) {
            return $this->libraryUnavailableResponse();
        }
    }

    /** GET /api/spell-library/health */
    public function health(): JsonResponse
    {
        try {
            $status = $this->client->health() ? 'ok' : 'unreachable';
            return response()->json(['status' => $status]);
        } catch (SpellLibraryUnavailableException) {
            return $this->libraryUnavailableResponse();
        }
    }

    private function libraryUnavailableResponse(): JsonResponse
    {
        return response()->json(
            ['error' => ['code' => 'LIBRARY_UNREACHABLE', 'message' => 'The spell library is currently unavailable.']],
            503
        );
    }
}
