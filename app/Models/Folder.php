<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

    /**
     * Get the parent folder.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Get the child folders.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * Get all nested children (recursive).
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Get all nested children with NPC and template counts (recursive, eager-loaded).
     */
    public function allChildrenWithCounts(): HasMany
    {
        return $this->children()
            ->withCount(['actualNpcs', 'templates'])
            ->with('allChildrenWithCounts')
            ->orderBy('name');
    }

    /**
     * Get all descendant folder IDs for a given folder.
     */
    public static function descendantIds(int $folderId): array
    {
        $folder = static::with('children')->find($folderId);

        if (! $folder) {
            return [];
        }

        $ids = [];

        foreach ($folder->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, static::descendantIds($child->id));
        }

        return $ids;
    }

    /**
     * Get a depth-first [id => 'Full / Path / Label'] collection for folder pickers.
     * Optionally exclude a folder and its entire subtree.
     */
    public static function treeOptions(?int $excludeFolderId = null): Collection
    {
        $all = static::orderBy('name')->get();
        // groupBy converts null parent_id to '' key in the grouped collection
        $grouped = $all->groupBy('parent_id');

        $excludeIds = [];
        if ($excludeFolderId !== null) {
            $excludeIds[] = $excludeFolderId;

            // Gather descendant ids in-memory from the already-loaded $grouped
            // map instead of querying per depth level.
            $collectDescendants = function (int $parentId) use (&$collectDescendants, &$excludeIds, $grouped): void {
                foreach ($grouped->get($parentId) ?? collect() as $child) {
                    $excludeIds[] = $child->id;
                    $collectDescendants($child->id);
                }
            };
            $collectDescendants($excludeFolderId);
        }

        $result = collect();

        $walk = function (mixed $parentKey, string $prefix) use (&$walk, $grouped, $excludeIds, &$result): void {
            $children = $grouped->get($parentKey) ?? collect();
            foreach ($children as $folder) {
                if (in_array($folder->id, $excludeIds)) {
                    continue;
                }
                $label = $prefix === '' ? $folder->name : $prefix . ' / ' . $folder->name;
                $result->put($folder->id, $label);
                // Recurse using the integer folder id as the parent key
                $walk($folder->id, $label);
            }
        };

        // Root folders have parent_id = null, which groupBy stores under '' key
        $walk('', '');

        return $result;
    }

    /**
     * Get the NPCs in this folder.
     */
    public function npcs(): HasMany
    {
        return $this->hasMany(Npc::class);
    }

    /**
     * Get the non-template NPCs in this folder.
     */
    public function actualNpcs(): HasMany
    {
        return $this->hasMany(Npc::class)->npcs();
    }

    /**
     * Get the templates in this folder.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Npc::class)->templates();
    }

    /**
     * Get the non-template NPC count for this folder.
     */
    public function actualNpcCount(): int
    {
        if (isset($this->actual_npcs_count)) {
            return $this->actual_npcs_count;
        }

        if ($this->relationLoaded('actualNpcs')) {
            return $this->actualNpcs->count();
        }

        return $this->actualNpcs()->count();
    }

    /**
     * Get the template count for this folder.
     */
    public function templateCount(): int
    {
        if (isset($this->templates_count)) {
            return $this->templates_count;
        }

        if ($this->relationLoaded('templates')) {
            return $this->templates->count();
        }

        return $this->templates()->count();
    }

    /**
     * Build a flat map of folder id → full path string for all folders.
     * Performs a single Folder::all() query and resolves paths in-memory,
     * avoiding N+1 when rendering paths for many NPCs in a list response.
     *
     * Example: [3 => "Campaigns / Waterdeep", 1 => "Campaigns"]
     *
     * @return array<int, string>
     */
    public static function buildPathMap(): array
    {
        $all = static::all()->keyBy('id');
        $map = [];

        foreach ($all as $folder) {
            $path    = [];
            $current = $folder;
            $visited = [];

            // Walk parents; stop on cycle (parent_id points to a visited folder)
            while ($current !== null && !in_array($current->id, $visited, strict: true)) {
                array_unshift($path, $current->name);
                $visited[] = $current->id;
                $current   = $current->parent_id !== null ? $all->get($current->parent_id) : null;
            }

            $map[$folder->id] = implode(' / ', $path);
        }

        return $map;
    }

    /**
     * Get the breadcrumb path to this folder.
     */
    /**
     * Maximum parent-chain depth walked when building a breadcrumb.
     * Guards against a corrupted parent_id cycle looping forever — the
     * same cap FolderController::MAX_DEPTH uses for the folder tree.
     */
    private const MAX_BREADCRUMB_DEPTH = 20;

    public function getBreadcrumbAttribute(): array
    {
        $breadcrumb = [];
        $folder = $this;
        $depth = 0;

        while ($folder && $depth < self::MAX_BREADCRUMB_DEPTH) {
            array_unshift($breadcrumb, $folder);
            $folder = $folder->parent;
            $depth++;
        }

        return $breadcrumb;
    }
}
