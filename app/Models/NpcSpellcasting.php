<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpcSpellcasting extends Model
{
    use HasFactory;

    protected $table = 'npc_spellcasting';

    protected $fillable = [
        'npc_id',
        'ability',
        'spell_save_dc',
        'spell_attack_bonus',
        'caster_level',
        'spellcasting_notes',
        'spells',
    ];

    protected $casts = [
        'spells' => 'array',
    ];

    public const SPELLCASTING_ABILITIES = [
        'Intelligence',
        'Wisdom',
        'Charisma',
    ];

    /**
     * Get the NPC that owns this spellcasting.
     */
    public function npc(): BelongsTo
    {
        return $this->belongsTo(Npc::class);
    }

    /**
     * Get spells by level.
     */
    public function getSpellsByLevel(int $level): array
    {
        return $this->spells[$level] ?? [];
    }

    /**
     * Get cantrips.
     */
    public function getCantripsAttribute(): array
    {
        return $this->getSpellsByLevel(0);
    }
}
