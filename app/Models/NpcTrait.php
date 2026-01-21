<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpcTrait extends Model
{
    use HasFactory;

    protected $fillable = [
        'npc_id',
        'name',
        'description',
    ];

    /**
     * Get the NPC that owns this trait.
     */
    public function npc(): BelongsTo
    {
        return $this->belongsTo(Npc::class);
    }
}
