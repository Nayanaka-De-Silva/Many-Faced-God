<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpcInnateSpellEntry extends Model
{
    use HasFactory;

    public const USAGE_AT_WILL = 'AtWill';
    public const USAGE_PER_DAY = 'PerDay';

    protected $fillable = [
        'casting_profile_id',
        'spell_library_id',
        'spell_name',
        'usage',
        'uses_per_day',
        'restriction',
        'cast_level',
        'sort_order',
    ];

    /**
     * Get the casting profile this entry belongs to.
     * Explicit FK required: the column is 'casting_profile_id', not the
     * Laravel-guessed 'npc_casting_profile_id'.
     */
    public function castingProfile(): BelongsTo
    {
        return $this->belongsTo(NpcCastingProfile::class, 'casting_profile_id');
    }

    /**
     * Format this entry as a single display line.
     * Example: "Fire Bolt (at will)" or "Charm Person (3/day)"
     */
    public function getFormattedLineAttribute(): string
    {
        if ($this->usage === self::USAGE_AT_WILL) {
            return "{$this->spell_name} (at will)";
        }

        $n = $this->uses_per_day ?? 1;

        return "{$this->spell_name} ({$n}/day)";
    }
}
