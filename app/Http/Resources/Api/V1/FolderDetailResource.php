<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full folder representation used by the /folders list and show endpoints.
 * Shape: {id, name, path, breadcrumb, npcCount, templateCount, children}
 *
 * Intentionally separate from FolderResource (the compact {id, name, path}
 * used when a folder is embedded inside an NpcResource).
 */
class FolderDetailResource extends JsonResource
{
    /**
     * Precomputed id→path map, set by FolderController before serialization
     * to avoid N+1 path lookups.
     *
     * @var array<int, string>|null
     */
    public static ?array $folderPathMap = null;

    public function toArray(Request $request): array
    {
        /** @var Folder $folder */
        $folder = $this->resource;

        return [
            'id'            => $folder->id,
            'name'          => $folder->name,
            'path'          => static::$folderPathMap[$folder->id] ?? $folder->name,
            'breadcrumb'    => $this->buildBreadcrumb($folder),
            'npcCount'      => $folder->actualNpcCount(),
            'templateCount' => $folder->templateCount(),
            'children'      => $this->resource['children'] ?? [], // injected by controller tree builder
        ];
    }

    /**
     * Build a breadcrumb array [{id, name}] from the id→path map.
     * Falls back to walking the $folder->breadcrumb accessor (safe for show() single calls).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function buildBreadcrumb(Folder $folder): array
    {
        // Use breadcrumb accessor (walks parent chain; fine for single-item show())
        return array_map(
            fn (Folder $crumb): array => ['id' => $crumb->id, 'name' => $crumb->name],
            $folder->breadcrumb
        );
    }
}
