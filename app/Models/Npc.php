<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Npc extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'npc_type',
        'alignment',
        'armor_class',
        'armor_type',
        'hit_points',
        'hit_dice',
        'speed',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
        'saving_throw_proficiencies',
        'skill_proficiencies',
        'damage_vulnerabilities',
        'damage_resistances',
        'damage_immunities',
        'condition_immunities',
        'senses',
        'languages',
        'challenge_rating',
        'proficiency_bonus',
        'folder_id',
        'is_template',
    ];

    protected $casts = [
        'saving_throw_proficiencies' => 'array',
        'skill_proficiencies' => 'array',
        'damage_vulnerabilities' => 'array',
        'damage_resistances' => 'array',
        'damage_immunities' => 'array',
        'condition_immunities' => 'array',
        'senses' => 'array',
        'languages' => 'array',
        'is_template' => 'boolean',
    ];

    /**
     * D&D 5e Skills mapped to their ability scores.
     */
    public const SKILLS = [
        'Acrobatics' => 'dexterity',
        'Animal Handling' => 'wisdom',
        'Arcana' => 'intelligence',
        'Athletics' => 'strength',
        'Deception' => 'charisma',
        'History' => 'intelligence',
        'Insight' => 'wisdom',
        'Intimidation' => 'charisma',
        'Investigation' => 'intelligence',
        'Medicine' => 'wisdom',
        'Nature' => 'intelligence',
        'Perception' => 'wisdom',
        'Performance' => 'charisma',
        'Persuasion' => 'charisma',
        'Religion' => 'intelligence',
        'Sleight of Hand' => 'dexterity',
        'Stealth' => 'dexterity',
        'Survival' => 'wisdom',
    ];

    /**
     * D&D 5e Damage Types.
     */
    public const DAMAGE_TYPES = [
        'Bludgeoning',
        'Piercing',
        'Slashing',
        'Magical Bludgeoning',
        'Magical Piercing',
        'Magical Slashing',
        'Fire',
        'Cold',
        'Acid',
        'Force',
        'Lightning',
        'Necrotic',
        'Poison',
        'Psychic',
        'Radiant',
        'Thunder',
    ];

    /**
     * D&D 5e Conditions.
     */
    public const CONDITIONS = [
        'Blinded',
        'Charmed',
        'Deafened',
        'Frightened',
        'Grappled',
        'Incapacitated',
        'Invisible',
        'Paralyzed',
        'Petrified',
        'Poisoned',
        'Prone',
        'Restrained',
        'Stunned',
        'Unconscious',
        'Exhaustion',
    ];

    /**
     * D&D 5e Alignments.
     */
    public const ALIGNMENTS = [
        'Lawful Good',
        'Neutral Good',
        'Chaotic Good',
        'Lawful Neutral',
        'True Neutral',
        'Chaotic Neutral',
        'Lawful Evil',
        'Neutral Evil',
        'Chaotic Evil',
        'Unaligned',
    ];

    /**
     * Get the folder this NPC belongs to.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Get the NPC's traits.
     */
    public function traits(): HasMany
    {
        return $this->hasMany(NpcTrait::class);
    }

    /**
     * Get the NPC's actions.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(NpcAction::class);
    }

    /**
     * Get the NPC's spellcasting.
     */
    public function spellcasting(): HasOne
    {
        return $this->hasOne(NpcSpellcasting::class);
    }

    /**
     * Calculate ability modifier.
     */
    public static function calculateModifier(int $score): int
    {
        return (int) floor(($score - 10) / 2);
    }

    /**
     * Get the strength modifier.
     */
    public function getStrengthModifierAttribute(): int
    {
        return self::calculateModifier($this->strength);
    }

    /**
     * Get the dexterity modifier.
     */
    public function getDexterityModifierAttribute(): int
    {
        return self::calculateModifier($this->dexterity);
    }

    /**
     * Get the constitution modifier.
     */
    public function getConstitutionModifierAttribute(): int
    {
        return self::calculateModifier($this->constitution);
    }

    /**
     * Get the intelligence modifier.
     */
    public function getIntelligenceModifierAttribute(): int
    {
        return self::calculateModifier($this->intelligence);
    }

    /**
     * Get the wisdom modifier.
     */
    public function getWisdomModifierAttribute(): int
    {
        return self::calculateModifier($this->wisdom);
    }

    /**
     * Get the charisma modifier.
     */
    public function getCharismaModifierAttribute(): int
    {
        return self::calculateModifier($this->charisma);
    }

    /**
     * Format modifier for display (e.g., +2 or -1).
     */
    public static function formatModifier(int $modifier): string
    {
        return $modifier >= 0 ? "+{$modifier}" : (string) $modifier;
    }

    /**
     * Get passive perception.
     */
    public function getPassivePerceptionAttribute(): int
    {
        $proficiencyBonus = $this->proficiency_bonus ?? 2;
        $skills = $this->skill_proficiencies ?? [];
        
        $bonus = in_array('Perception', $skills) ? $proficiencyBonus : 0;
        
        return 10 + $this->wisdom_modifier + $bonus;
    }

    /**
     * Roll hit points based on hit dice.
     */
    public static function rollHitPoints(string $hitDice): int
    {
        // Parse dice notation like "4d8+8"
        if (!preg_match('/^(\d+)d(\d+)([+-]\d+)?$/', $hitDice, $matches)) {
            return 0;
        }

        $numDice = (int) $matches[1];
        $diceSize = (int) $matches[2];
        $modifier = isset($matches[3]) ? (int) $matches[3] : 0;

        $total = 0;
        for ($i = 0; $i < $numDice; $i++) {
            $total += rand(1, $diceSize);
        }

        return max(1, $total + $modifier);
    }

    /**
     * Clone this NPC.
     */
    public function duplicate(): self
    {
        $clone = $this->replicate();
        $clone->name = $this->name . ' (Copy)';
        $clone->is_template = false;
        $clone->save();

        // Clone related records
        foreach ($this->traits as $trait) {
            $clone->traits()->create($trait->only(['name', 'description']));
        }

        foreach ($this->actions as $action) {
            $clone->actions()->create($action->only(['name', 'description', 'action_type', 'legendary_cost']));
        }

        if ($this->spellcasting) {
            $clone->spellcasting()->create(
                $this->spellcasting->only(['ability', 'spell_save_dc', 'spell_attack_bonus', 'caster_level', 'spellcasting_notes', 'spells'])
            );
        }

        return $clone;
    }

    /**
     * Scope a query to only include templates.
     */
    public function scopeTemplates($query)
    {
        return $query->where('is_template', true);
    }

    /**
     * Scope a query to only include non-templates.
     */
    public function scopeNpcs($query)
    {
        return $query->where('is_template', false);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where('name', 'like', "%{$term}%");
    }

    /**
     * Scope a query to filter by challenge rating.
     */
    public function scopeByChallengeRating($query, string $cr)
    {
        return $query->where('challenge_rating', $cr);
    }
}
