<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpcNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'npc_id',
        'title',
        'description',
        'sort_order',
    ];

    /**
     * Get the NPC that owns this note card.
     */
    public function npc(): BelongsTo
    {
        return $this->belongsTo(Npc::class);
    }
}
