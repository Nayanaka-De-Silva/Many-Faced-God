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
     * Get the breadcrumb path to this folder.
     */
    public function getBreadcrumbAttribute(): array
    {
        $breadcrumb = [];
        $folder = $this;

        while ($folder) {
            array_unshift($breadcrumb, $folder);
            $folder = $folder->parent;
        }

        return $breadcrumb;
    }
}
