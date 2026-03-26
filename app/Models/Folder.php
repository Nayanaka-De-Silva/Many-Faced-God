<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
