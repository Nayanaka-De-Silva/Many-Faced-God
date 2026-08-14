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
 * Read-only Template statblock endpoints for API v1.
 * Base scope: Npc::templates() (is_template = true).
 * Reuses NpcResource — same statblock shape, just filtered to templates.
 */
class TemplateController extends Controller
{
    private const ALLOWED_PARAMS = [
        'page', 'pageSize', 'sort', 'direction',
        'search', 'challengeRating', 'folderId', 'includeDescendants',
        'ids',
    ];

    private const SORT_MAP = [
        'name'            => 'name',
        'challengeRating' => null,
        'armorClass'      => 'armor_class',
        'hitPoints'       => 'hit_points',
        'createdAt'       => 'created_at',
        'updatedAt'       => 'updated_at',
    ];

    private const DEFAULT_PAGE_SIZE = 25;
    private const MAX_PAGE_SIZE     = 50;
    private const MAX_IDS_COUNT     = 100;

    // ── GET /api/v1/templates ─────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // Only scalar values are meaningful params here. A malformed shape
        // like ?sort[]=name would otherwise reach array_key_exists()/
        // strtolower() below as an array and throw an uncaught TypeError —
        // treat it the same as an unknown param instead: ignored silently.
        $params = array_filter(
            $request->only(self::ALLOWED_PARAMS),
            fn ($value): bool => is_scalar($value)
        );

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

        $query = Npc::templates()->with(Npc::STATBLOCK_EAGER_LOADS);

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        } else {
            if (!empty($params['search'])) {
                $query->search($params['search']);
            }

            if (!empty($params['challengeRating'])) {
                $query->byChallengeRating($params['challengeRating']);
            }

            if (!empty($params['folderId'])) {
                $folderId           = (int) $params['folderId'];
                $includeDescendants = filter_var($params['includeDescendants'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($includeDescendants) {
                    $folderIds = array_merge([$folderId], Folder::descendantIds($folderId));
                    $query->whereIn('folder_id', $folderIds);
                } else {
                    $query->where('folder_id', $folderId);
                }
            }
        }

        $sort = $params['sort'] ?? 'name';
        if ($sort === 'challengeRating') {
            $query->orderByRaw(ChallengeRating::orderByRankSql() . ' ' . $direction);
        } elseif ($sort === 'name') {
            $query->orderByRaw('LOWER(name) ' . $direction);
        } else {
            $query->orderBy(self::SORT_MAP[$sort], $direction);
        }

        $pageSize   = min((int) ($params['pageSize'] ?? self::DEFAULT_PAGE_SIZE), self::MAX_PAGE_SIZE);
        $pageSize   = max(1, $pageSize);
        $page       = max(1, (int) ($params['page'] ?? 1));
        $total      = $query->count();
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
        $templates  = $query->forPage($page, $pageSize)->get();

        NpcResource::$folderPathMap = Folder::buildPathMap();

        $listEtag = md5(implode(':', [
            'templates',
            $templates->map(fn (Npc $npc): int => $npc->freshestUpdatedAt()?->timestamp ?? time())->max() ?? '0',
            $total,
            http_build_query($params),
        ]));

        return response()
            ->json([
                'data' => NpcResource::collection($templates),
                'meta' => [
                    'page'       => $page,
                    'pageSize'   => $pageSize,
                    'totalItems' => $total,
                    'totalPages' => $totalPages,
                ],
            ])
            ->setEtag($listEtag, weak: true);
    }

    // ── GET /api/v1/templates/{template} ─────────────────────────────────────

    /**
     * Returns a single template by id. Scoped to is_template = true.
     * A non-template id 404s here — it exists in the table but not in this scope.
     */
    public function show(int $template): JsonResponse
    {
        $npcModel = Npc::templates()
            ->with(Npc::STATBLOCK_EAGER_LOADS)
            ->find($template);

        if ($npcModel === null) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Template not found.'],
            ], 404);
        }

        NpcResource::$folderPathMap = Folder::buildPathMap();

        return response()
            ->json(['data' => new NpcResource($npcModel)])
            ->setEtag(md5('npc:' . $npcModel->id . ':' . ($npcModel->freshestUpdatedAt()?->timestamp ?? time())));
    }
}
