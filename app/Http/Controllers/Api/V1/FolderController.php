<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FolderDetailResource;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Read-only folder tree endpoints for API v1.
 */
class FolderController extends Controller
{
    /**
     * Maximum recursion depth when building the folder tree.
     * Caps allChildren() traversal — Folder::allChildren() has no built-in
     * cycle guard, so a corrupted parent_id loop would recurse forever without this.
     */
    private const MAX_DEPTH = 20;

    // ── GET /api/v1/folders ───────────────────────────────────────────────────

    /**
     * Returns the full folder tree with NPC and template counts per folder.
     * Loads all folders in one query and builds the tree in PHP, so there are no
     * per-folder child queries and no N+1 count lookups (withCount handles it).
     */
    public function index(): JsonResponse
    {
        $folders = Folder::withCount(['actualNpcs as actual_npcs_count', 'templates as templates_count'])
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $folderPathMap = Folder::buildPathMap();
        FolderDetailResource::$folderPathMap = $folderPathMap;

        $tree = $this->buildTree($folders, null, 0);

        return response()->json(['data' => $tree]);
    }

    /**
     * Recursively build the folder tree from the preloaded collection.
     * Caps depth at MAX_DEPTH to prevent infinite recursion on cycles.
     *
     * @param  Collection<int, Folder>  $all  All folders keyed by id
     * @param  int|null  $parentId             Parent id to find children of (null = roots)
     * @param  int  $depth                     Current recursion depth
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $all, ?int $parentId, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [];
        }

        return $all
            ->filter(fn (Folder $f): bool => $f->parent_id === $parentId)
            ->map(fn (Folder $f): array => [
                'id'            => $f->id,
                'name'          => $f->name,
                'path'          => FolderDetailResource::$folderPathMap[$f->id] ?? $f->name,
                'npcCount'      => $f->actualNpcCount(),
                'templateCount' => $f->templateCount(),
                'children'      => $this->buildTree($all, $f->id, $depth + 1),
            ])
            ->values()
            ->all();
    }

    // ── GET /api/v1/folders/{id} ──────────────────────────────────────────────

    /**
     * Returns a single folder with its breadcrumb path, counts, and immediate children.
     * Immediate children only — not deeply nested (use index() for the full tree).
     *
     * $folder->breadcrumb is an accessor (property, not method) that walks the
     * parent chain. Safe for single-item show() since it's only one folder.
     */
    public function show(int $id): JsonResponse
    {
        $folder = Folder::withCount(['actualNpcs as actual_npcs_count', 'templates as templates_count'])
            ->with(['parent.parent.parent.parent.parent.parent.parent.parent']) // load ~8 ancestor levels for breadcrumb
            ->find($id);

        if ($folder === null) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Folder not found.'],
            ], 404);
        }

        $folderPathMap = Folder::buildPathMap();
        FolderDetailResource::$folderPathMap = $folderPathMap;

        // Load immediate children with their counts
        $children = Folder::withCount(['actualNpcs as actual_npcs_count', 'templates as templates_count'])
            ->where('parent_id', $id)
            ->orderBy('name')
            ->get();

        $childrenData = $children->map(fn (Folder $child): array => [
            'id'            => $child->id,
            'name'          => $child->name,
            'path'          => $folderPathMap[$child->id] ?? $child->name,
            'npcCount'      => $child->actualNpcCount(),
            'templateCount' => $child->templateCount(),
        ])->values()->all();

        // Build breadcrumb from the accessor (walks parent chain via eager-loaded relation)
        $breadcrumb = array_map(
            fn (Folder $crumb): array => ['id' => $crumb->id, 'name' => $crumb->name],
            $folder->breadcrumb
        );

        return response()->json([
            'data' => [
                'id'            => $folder->id,
                'name'          => $folder->name,
                'path'          => $folderPathMap[$folder->id] ?? $folder->name,
                'breadcrumb'    => $breadcrumb,
                'npcCount'      => $folder->actualNpcCount(),
                'templateCount' => $folder->templateCount(),
                'children'      => $childrenData,
            ],
        ]);
    }
}
