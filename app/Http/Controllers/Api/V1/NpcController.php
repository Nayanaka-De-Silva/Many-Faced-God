<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NpcResource;
use App\Models\Folder;
use App\Models\Npc;
use App\Support\ChallengeRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only NPC statblock endpoints for API v1.
 * Base scope: Npc::npcs() (is_template = false).
 */
class NpcController extends Controller
{
    /**
     * Allowlisted camelCase query params.
     * Unknown params are silently ignored — never passed to Eloquent.
     */
    private const ALLOWED_PARAMS = [
        'page', 'pageSize', 'sort', 'direction',
        'search', 'challengeRating', 'folderId', 'includeDescendants',
        'ids',
    ];

    /**
     * Valid sort field names (camelCase) mapped to their SQL equivalents.
     * challengeRating uses orderByRaw() with ChallengeRating::orderByRankSql().
     */
    private const SORT_MAP = [
        'name'            => 'name',
        'challengeRating' => null, // handled specially with orderByRaw
        'armorClass'      => 'armor_class',
        'hitPoints'       => 'hit_points',
        'createdAt'       => 'created_at',
        'updatedAt'       => 'updated_at',
    ];

    private const DEFAULT_PAGE_SIZE = 25;
    private const MAX_PAGE_SIZE     = 50;
    private const MAX_IDS_COUNT     = 100;

    // ── GET /api/v1/npcs ──────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $params = $request->only(self::ALLOWED_PARAMS);

        // Validate sort and direction strictly — 400 for unrecognised values
        if (!empty($params['sort']) && !array_key_exists($params['sort'], self::SORT_MAP)) {
            return response()->json([
                'error' => ['code' => 'INVALID_SORT', 'message' => "Invalid sort field: '{$params['sort']}'."],
            ], 400);
        }

        $direction = strtolower($params['direction'] ?? 'asc');
        if (!in_array($direction, ['asc', 'desc'], strict: true)) {
            return response()->json([
                'error' => ['code' => 'INVALID_QUERY', 'message' => "direction must be 'asc' or 'desc'."],
            ], 400);
        }

        // Parse and validate ids parameter
        $ids = null;
        if (!empty($params['ids'])) {
            $ids = array_filter(
                array_map('intval', explode(',', $params['ids'])),
                fn (int $id): bool => $id > 0
            );

            if (count($ids) > self::MAX_IDS_COUNT) {
                return response()->json([
                    'error' => [
                        'code'    => 'INVALID_QUERY',
                        'message' => 'ids may contain at most ' . self::MAX_IDS_COUNT . ' values.',
                    ],
                ], 400);
            }
        }

        $query = Npc::npcs()->with(Npc::STATBLOCK_EAGER_LOADS);

        if ($ids !== null) {
            // When ids is present, filter params (search/CR/folder) are ignored.
            // sort/direction/page/pageSize still apply for consistent envelope shape.
            $query->whereIn('id', $ids);
        } else {
            // search — arrives as null when sent empty due to ConvertEmptyStringsToNull
            if (!empty($params['search'])) {
                $query->search($params['search']);
            }

            if (!empty($params['challengeRating'])) {
                $query->byChallengeRating($params['challengeRating']);
            }

            if (!empty($params['folderId'])) {
                $folderId          = (int) $params['folderId'];
                $includeDescendants = filter_var($params['includeDescendants'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($includeDescendants) {
                    $folderIds = array_merge([$folderId], Folder::descendantIds($folderId));
                    $query->whereIn('folder_id', $folderIds);
                } else {
                    $query->where('folder_id', $folderId);
                }
            }
        }

        // Apply sort
        $sort = $params['sort'] ?? 'name';
        if ($sort === 'challengeRating') {
            $query->orderByRaw(ChallengeRating::orderByRankSql() . ' ' . $direction);
        } elseif ($sort === 'name') {
            // Use LOWER() to ensure consistent case-insensitive ordering across SQLite (BINARY)
            // and MySQL (case-insensitive by default) — prevents test order non-determinism.
            $query->orderByRaw('LOWER(name) ' . $direction);
        } else {
            $query->orderBy(self::SORT_MAP[$sort], $direction);
        }

        // Pagination — pageSize clamped silently to MAX_PAGE_SIZE, no error
        $pageSize = min((int) ($params['pageSize'] ?? self::DEFAULT_PAGE_SIZE), self::MAX_PAGE_SIZE);
        $pageSize = max(1, $pageSize);
        $page     = max(1, (int) ($params['page'] ?? 1));

        $total       = $query->count();
        $totalPages  = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
        $npcs        = $query->forPage($page, $pageSize)->get();

        // Precompute folder path map once per request — prevents N+1 path lookups
        NpcResource::$folderPathMap = Folder::buildPathMap();

        $listEtag = md5(implode(':', [
            'npcs',
            $npcs->map(fn (Npc $npc): int => $npc->freshestUpdatedAt()->timestamp)->max() ?? '0',
            $total,
            http_build_query($params),
        ]));

        return response()
            ->json([
                'data' => NpcResource::collection($npcs),
                'meta' => [
                    'page'       => $page,
                    'pageSize'   => $pageSize,
                    'totalItems' => $total,
                    'totalPages' => $totalPages,
                ],
            ])
            ->setEtag($listEtag, weak: true);
    }

    // ── GET /api/v1/npcs/{npc} ────────────────────────────────────────────────

    /**
     * Returns a single NPC by id. Scoped to is_template = false.
     * A template id 404s here — it exists in the table but not in this scope.
     */
    public function show(int $npc): JsonResponse
    {
        $npcModel = Npc::npcs()
            ->with(Npc::STATBLOCK_EAGER_LOADS)
            ->find($npc);

        if ($npcModel === null) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'NPC not found.'],
            ], 404);
        }

        NpcResource::$folderPathMap = Folder::buildPathMap();

        return response()
            ->json(['data' => new NpcResource($npcModel)])
            ->setEtag(md5('npc:' . $npcModel->id . ':' . $npcModel->freshestUpdatedAt()->timestamp));
    }
}
