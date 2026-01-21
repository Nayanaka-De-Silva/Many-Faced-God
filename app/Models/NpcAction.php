<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpcAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'npc_id',
        'name',
        'description',
        'action_type',
        'legendary_cost',
    ];

    public const ACTION_TYPES = [
        'action' => 'Action',
        'bonus_action' => 'Bonus Action',
        'reaction' => 'Reaction',
        'legendary_action' => 'Legendary Action',
    ];

    /**
     * Get the NPC that owns this action.
     */
    public function npc(): BelongsTo
    {
        return $this->belongsTo(Npc::class);
    }

    /**
     * Scope a query to only include standard actions.
     */
    public function scopeStandardActions($query)
    {
        return $query->where('action_type', 'action');
    }

    /**
     * Scope a query to only include bonus actions.
     */
    public function scopeBonusActions($query)
    {
        return $query->where('action_type', 'bonus_action');
    }

    /**
     * Scope a query to only include reactions.
     */
    public function scopeReactions($query)
    {
        return $query->where('action_type', 'reaction');
    }

    /**
     * Scope a query to only include legendary actions.
     */
    public function scopeLegendaryActions($query)
    {
        return $query->where('action_type', 'legendary_action');
    }
}
