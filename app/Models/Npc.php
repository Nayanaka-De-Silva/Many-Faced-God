<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\NpcCastingProfile;
use App\Models\NpcNote;
use Illuminate\Support\Str;

class Npc extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'npc_type',
        'alignment',
        'personality_traits',
        'ideals',
        'bonds',
        'flaws',
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

    public const TEMPLATE_HIT_POINT_MODE_TEMPLATE = 'template';
    public const TEMPLATE_HIT_POINT_MODE_ROLL = 'roll';
    public const TEMPLATE_HIT_POINT_MODES = [
        self::TEMPLATE_HIT_POINT_MODE_TEMPLATE,
        self::TEMPLATE_HIT_POINT_MODE_ROLL,
    ];

    /**
     * Relations needed to render the show/statblock view. Shared by NpcController::show()
     * and TemplateController::show() (templates are npcs rows too) so the two can't drift
     * out of sync — as happened before this constant existed, when templates silently
     * rendered no spellcasting section because only one of the two eager-load calls knew
     * about the relation.
     */
    public const STATBLOCK_EAGER_LOADS = ['folder', 'traits', 'actions', 'spellcasting', 'castingProfiles.innateEntries', 'noteCards'];

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
     * Sense measurement categories: key is the stored value, value is the human label.
     */
    public const SENSE_CATEGORIES = [
        'ft' => 'ft.',
        'dc' => 'DC',
        'other' => 'Other',
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
     * Get the NPC's casting profiles (Phase 2+ spellcasting system), ordered by sort_order.
     */
    public function castingProfiles(): HasMany
    {
        return $this->hasMany(NpcCastingProfile::class)->orderBy('sort_order');
    }

    /**
     * Get the NPC's note cards, ordered by sort_order.
     */
    public function noteCards(): HasMany
    {
        return $this->hasMany(NpcNote::class)->orderBy('sort_order');
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
     * Format each sense entry for display using its category.
     * Legacy rows with no category key default to 'ft' behaviour.
     *
     * @return string[]
     */
    public function getFormattedSensesAttribute(): array
    {
        return array_map(function (array $sense): string {
            $type = $sense['type'] ?? '';
            $range = $sense['range'] ?? '';
            $category = $sense['category'] ?? 'ft';

            return match ($category) {
                'ft' => trim("{$type} {$range}") . ' ft.',
                'dc', 'other' => trim("{$type} {$range}"),
                default => trim("{$type} {$range}") . ' ft.',
            };
        }, $this->senses ?? []);
    }

    /**
     * Calculate the deterministic average hit points for a given dice string.
     *
     * Uses the mathematical average of each die (floor((diceSize + 1) / 2) per die),
     * plus any flat modifier. Returns null for unparseable input, minimum 1 otherwise.
     * Safe to call from API code — no rand() involved.
     */
    public static function averageHitPoints(string $hitDice): ?int
    {
        if (!preg_match('/^(\d+)d(\d+)([+-]\d+)?$/', $hitDice, $matches)) {
            return null;
        }

        $numDice  = (int) $matches[1];
        $diceSize = (int) $matches[2];
        $modifier = isset($matches[3]) ? (int) $matches[3] : 0;

        $average = (int) floor($numDice * ($diceSize + 1) / 2 + $modifier);

        return max(1, $average);
    }

    /**
     * The latest updated_at across this NPC and all of its statblock children
     * (traits, actions, spellcasting/castingProfiles+innateEntries).
     *
     * Used for ETag computation. There is no $touches anywhere in this app
     * (deliberately — see STATBLOCK_EAGER_LOADS docblock), so editing a
     * child row does NOT bump npcs.updated_at. Callers must scan the
     * children directly instead of trusting the NPC row's own timestamp.
     *
     * Relies on STATBLOCK_EAGER_LOADS already being loaded — issues no
     * additional queries.
     */
    public function freshestUpdatedAt(): ?\Illuminate\Support\Carbon
    {
        $timestamps = collect([$this->updated_at])
            ->merge($this->traits->pluck('updated_at'))
            ->merge($this->actions->pluck('updated_at'))
            ->merge($this->spellcasting !== null ? [$this->spellcasting->updated_at] : [])
            ->merge($this->castingProfiles->pluck('updated_at'))
            ->merge($this->castingProfiles->flatMap->innateEntries->pluck('updated_at'))
            ->merge($this->noteCards->pluck('updated_at'))
            ->filter();

        // Nullable: a row with every timestamp null (e.g. seeded via a raw
        // insert that bypassed Eloquent's automatic timestamps) has nothing
        // to compute a max from. Callers must handle null explicitly rather
        // than assume a timestamp always exists.
        return $timestamps->max();
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
     * Determine whether this template needs a hit point choice before creating an NPC.
     */
    public function requiresTemplateHitPointChoice(): bool
    {
        return $this->is_template
            && filled($this->hit_points)
            && filled($this->hit_dice);
    }

    /**
     * Apply the selected hit point mode when creating an NPC from a template.
     */
    public function applyTemplateHitPointMode(?string $mode): void
    {
        if (! $this->requiresTemplateHitPointChoice()) {
            return;
        }

        if ($mode === self::TEMPLATE_HIT_POINT_MODE_ROLL) {
            $this->hit_points = self::rollHitPoints($this->hit_dice);
        }
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
            $clone->actions()->create($action->only([
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
            ]));
        }

        if ($this->spellcasting) {
            $clone->spellcasting()->create(
                $this->spellcasting->only(['ability', 'spell_save_dc', 'spell_attack_bonus', 'caster_level', 'spellcasting_notes', 'spells'])
            );
        }

        foreach ($this->noteCards as $note) {
            $clone->noteCards()->create($note->only(['title', 'description', 'sort_order']));
        }

        // Clone casting profiles and their nested innate entries. Eager-load both levels up
        // front so this doesn't issue one extra query per profile for its innate entries.
        foreach ($this->castingProfiles()->with('innateEntries')->get() as $profile) {
            $cloneProfile = $clone->castingProfiles()->create($profile->only([
                'casting_type',
                'spellcasting_ability',
                'save_dc',
                'attack_bonus',
                'psionics',
                'source',
                'homebrew',
                'caster_level',
                'source_class',
                'slots',
                'slot_level',
                'slot_count',
                'race_or_origin',
                'cantrips',
                'spells_known_or_prepared',
                'sort_order',
            ]));

            foreach ($profile->innateEntries as $entry) {
                $cloneProfile->innateEntries()->create($entry->only([
                    'spell_library_id',
                    'spell_name',
                    'usage',
                    'uses_per_day',
                    'restriction',
                    'cast_level',
                    'sort_order',
                ]));
            }
        }

        return $clone;
    }

    /**
     * Get a compact preview from the first note card for card-style displays.
     * Returns "Title — first sentence(s) of description", or title alone when
     * description is blank, or null when there are no note cards.
     */
    public function notePreview(int $limit = 160): ?string
    {
        $cards = $this->noteCards;

        if ($cards === null || $cards->isEmpty()) {
            return null;
        }

        $first = $cards->first();
        $title = Str::squish($first->title ?? '');

        if (blank($first->description)) {
            return Str::limit($title, $limit);
        }

        $description = Str::squish($first->description);
        preg_match('/^(.+?[.!?](?:\s+.+?[.!?])?)/u', $description, $matches);
        $descPreview = $matches[1] ?? $description;

        return Str::limit("{$title} — {$descPreview}", $limit);
    }

    /**
     * Derive a flat notes string from all note cards joined in sort order.
     * Used by the public API to keep the legacy `notes` key non-breaking.
     * Returns null when there are no cards.
     */
    public function notesText(): ?string
    {
        $cards = $this->noteCards;

        if ($cards === null || $cards->isEmpty()) {
            return null;
        }

        return $cards->map(function (NpcNote $card): string {
            return filled($card->description)
                ? "{$card->title}\n\n{$card->description}"
                : $card->title;
        })->join("\n\n");
    }

    /**
     * Determine whether the NPC has any character note fields populated.
     */
    public function hasCharacterNotes(): bool
    {
        return filled($this->personality_traits)
            || filled($this->ideals)
            || filled($this->bonds)
            || filled($this->flaws);
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
