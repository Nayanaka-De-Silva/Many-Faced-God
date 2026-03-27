<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NpcAction extends Model
{
    use HasFactory;

    public const TYPE_ACTION = 'action';
    public const TYPE_BONUS_ACTION = 'bonus_action';
    public const TYPE_REACTION = 'reaction';
    public const TYPE_LEGENDARY_ACTION = 'legendary_action';
    public const TYPE_ATTACK = 'attack_action';

    protected $fillable = [
        'npc_id',
        'name',
        'description',
        'action_type',
        'legendary_cost',
        'attack_kind',
        'attack_range_text',
        'attack_to_hit',
        'attack_target',
        'attack_hit',
        'attack_hit_2',
    ];

    protected $casts = [
        'legendary_cost' => 'integer',
        'attack_to_hit' => 'integer',
    ];

    public const ACTION_TYPES = [
        self::TYPE_ACTION => 'Action',
        self::TYPE_BONUS_ACTION => 'Bonus Action',
        self::TYPE_REACTION => 'Reaction',
        self::TYPE_LEGENDARY_ACTION => 'Legendary Action',
        self::TYPE_ATTACK => 'Attack Action',
    ];

    public const ATTACK_KINDS = [
        'melee' => 'Melee',
        'ranged' => 'Ranged',
    ];

    public const ATTACK_FIELDS = [
        'attack_kind',
        'attack_range_text',
        'attack_to_hit',
        'attack_target',
        'attack_hit',
        'attack_hit_2',
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
        return $query->where('action_type', self::TYPE_ACTION);
    }

    /**
     * Scope a query to only include bonus actions.
     */
    public function scopeBonusActions($query)
    {
        return $query->where('action_type', self::TYPE_BONUS_ACTION);
    }

    /**
     * Scope a query to only include reactions.
     */
    public function scopeReactions($query)
    {
        return $query->where('action_type', self::TYPE_REACTION);
    }

    /**
     * Scope a query to only include legendary actions.
     */
    public function scopeLegendaryActions($query)
    {
        return $query->where('action_type', self::TYPE_LEGENDARY_ACTION);
    }

    /**
     * Scope a query to only include attack actions.
     */
    public function scopeAttackActions($query)
    {
        return $query->where('action_type', self::TYPE_ATTACK);
    }

    /**
     * Determine if the action is an attack action.
     */
    public function isAttackAction(): bool
    {
        return $this->action_type === self::TYPE_ATTACK;
    }

    /**
     * Get the formatted D&D attack line.
     */
    public function getFormattedAttackLineAttribute(): ?string
    {
        if (
            !$this->isAttackAction() ||
            blank($this->attack_kind) ||
            blank($this->attack_range_text) ||
            is_null($this->attack_to_hit) ||
            blank($this->attack_target)
        ) {
            return null;
        }

        $attackKind = self::ATTACK_KINDS[$this->attack_kind] ?? Str::headline((string) $this->attack_kind);
        $rangeLabel = $this->attack_kind === 'ranged' ? 'Range' : 'Reach';

        return implode(', ', [
            "{$attackKind} Weapon Attack: " . Npc::formatModifier($this->attack_to_hit),
            "{$rangeLabel} {$this->attack_range_text}",
            $this->attack_target,
        ]);
    }

    /**
     * Get the formatted attack hit line.
     */
    public function getFormattedHitLineAttribute(): ?string
    {
        if (!$this->isAttackAction() || blank($this->attack_hit)) {
            return null;
        }

        $line = "Hit: {$this->attack_hit}";

        if (filled($this->attack_hit_2)) {
            $line .= " (plus {$this->attack_hit_2})";
        }

        return $line;
    }
}
