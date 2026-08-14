<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact folder representation used when embedding a folder inside an NpcResource.
 * Shape: {id, name, path}
 *
 * This is intentionally different from FolderDetailResource, which is the full
 * representation used by the /folders endpoints (includes children, counts, breadcrumb).
 * Do not merge these two shapes into one class.
 *
 * The "path" field requires a precomputed id→path map set on NpcResource::$folderPathMap
 * by the controller before the resource collection is serialized, to avoid N+1 lookups.
 */
class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // NpcResource sets this static map from Folder::buildPathMap() so all
        // FolderResource instances in a list share the same single-query result.
        $path = NpcResource::$folderPathMap[$this->id] ?? $this->name;

        return [
            'id'   => $this->id,
            'name' => $this->name,
            'path' => $path,
        ];
    }
}
