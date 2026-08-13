<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class NpcCastingProfile extends Model
{
    use HasFactory;

    // Casting type discriminator constants
    public const TYPE_INNATE = 'Innate';
    public const TYPE_SPELLCASTING = 'Spellcasting';
    public const TYPE_PACT_MAGIC = 'PactMagic';

    /** Human-readable labels for form selects. */
    public const CASTING_TYPES = [
        self::TYPE_INNATE => 'Innate',
        self::TYPE_SPELLCASTING => 'Spellcasting',
        self::TYPE_PACT_MAGIC => 'Pact Magic',
    ];

    /** Valid spellcasting abilities (mirrors NpcSpellcasting::SPELLCASTING_ABILITIES). */
    public const ABILITIES = [
        'Intelligence',
        'Wisdom',
        'Charisma',
    ];

    /**
     * For each casting type: the list of column names that should be set to
     * null by the sanitizer because they do not apply to that type.
     * Used by NpcController::sanitizeCastingProfiles().
     */
    public const FIELDS_BY_TYPE = [
        self::TYPE_INNATE => [
            'caster_level',
            'source_class',
            'slots',
            'slot_level',
            'slot_count',
            'cantrips',
            'spells_known_or_prepared',
        ],
        self::TYPE_SPELLCASTING => [
            'slot_level',
            'slot_count',
            'race_or_origin',
        ],
        self::TYPE_PACT_MAGIC => [
            'slots',
            'source_class',
            'race_or_origin',
            'psionics', // psionics only applies to Innate + Spellcasting, per the schema.
        ],
    ];

    protected $fillable = [
        'npc_id',
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
    ];

    protected $casts = [
        'slots' => 'array',
        'cantrips' => 'array',
        'spells_known_or_prepared' => 'array',
        'psionics' => 'boolean',
        'homebrew' => 'boolean',
    ];

    /**
     * Get the NPC that owns this casting profile.
     */
    public function npc(): BelongsTo
    {
        return $this->belongsTo(Npc::class);
    }

    /**
     * Get the innate spell entries for this profile, ordered by sort_order.
     * Explicit FK required because the column name is 'casting_profile_id', not
     * the Laravel-guessed 'npc_casting_profile_id'.
     */
    public function innateEntries(): HasMany
    {
        return $this->hasMany(NpcInnateSpellEntry::class, 'casting_profile_id')->orderBy('sort_order');
    }

    /**
     * At-will innate spell entries, in sort_order. Empty for non-Innate profiles.
     * Reads the loaded innateEntries relation, so eager loading still covers it.
     *
     * Named to avoid Eloquent's relation-property magic: a public method whose name
     * matches a property access (e.g. $profile->atWillEntries, no parens) would be
     * probed as a relationship and throw, since it doesn't return one.
     *
     * @return Collection<int, NpcInnateSpellEntry>
     */
    public function innateAtWillSpells(): Collection
    {
        if (! $this->isInnate()) {
            return collect();
        }

        return $this->innateEntries
            ->where('usage', NpcInnateSpellEntry::USAGE_AT_WILL)
            ->values();
    }

    /**
     * Innate spell entries that aren't at-will, grouped by uses_per_day and ordered
     * highest count first — the order official 5e statblocks use. A null
     * uses_per_day counts as 1. Empty for non-Innate profiles.
     *
     * Groups everything that isn't at-will, rather than matching USAGE_PER_DAY
     * exactly, so an unexpected future usage value still surfaces here instead of
     * silently disappearing from the statblock.
     *
     * See innateAtWillSpells() for why this isn't named *Entries().
     *
     * @return Collection<int, Collection<int, NpcInnateSpellEntry>>
     */
    public function innatePerDaySpellGroups(): Collection
    {
        if (! $this->isInnate()) {
            return collect();
        }

        return $this->innateEntries
            ->reject(fn (NpcInnateSpellEntry $entry): bool => $entry->usage === NpcInnateSpellEntry::USAGE_AT_WILL)
            ->groupBy(fn (NpcInnateSpellEntry $entry): int => $entry->uses_per_day ?? 1)
            ->sortKeysDesc();
    }

    /** Returns true when this is an Innate casting profile. */
    public function isInnate(): bool
    {
        return $this->casting_type === self::TYPE_INNATE;
    }

    /** Returns true when this is a Spellcasting casting profile. */
    public function isSpellcasting(): bool
    {
        return $this->casting_type === self::TYPE_SPELLCASTING;
    }

    /** Returns true when this is a Pact Magic casting profile. */
    public function isPactMagic(): bool
    {
        return $this->casting_type === self::TYPE_PACT_MAGIC;
    }

    /**
     * Slot recovery type for Pact Magic.
     * Returns 'ShortOrLongRest' for PactMagic (invariant per 5e rules), null otherwise.
     */
    public function getRecoveryAttribute(): ?string
    {
        return $this->isPactMagic() ? 'ShortOrLongRest' : null;
    }

    /**
     * Group Innate entries into "At will: ..." and "N/day each: ..." display lines,
     * ordered as official statblocks are: at-will first, then per-day descending.
     * Returns an empty array for non-Innate profiles.
     *
     * @return string[]
     */
    public function getFormattedInnateLinesAttribute(): array
    {
        $lines = [];

        $atWillEntries = $this->innateAtWillSpells();
        if ($atWillEntries->isNotEmpty()) {
            $lines[] = 'At will: ' . $this->joinEntryLabels($atWillEntries);
        }

        foreach ($this->innatePerDaySpellGroups() as $usesPerDay => $entries) {
            $lines[] = "{$usesPerDay}/day each: " . $this->joinEntryLabels($entries);
        }

        return $lines;
    }

    /**
     * Render a group of innate entries as a comma-separated "Spell (restriction)" list.
     *
     * @param  Collection<int, NpcInnateSpellEntry>  $entries
     */
    private function joinEntryLabels(Collection $entries): string
    {
        return $entries
            ->map(fn (NpcInnateSpellEntry $entry): string => filled($entry->restriction)
                ? "{$entry->spell_name} ({$entry->restriction})"
                : $entry->spell_name)
            ->implode(', ');
    }

    /**
     * Merge the slot map with leveled spells for statblock display (Spellcasting only).
     * Returns one row per level that has slots > 0 OR at least one assigned spell.
     * Rows with 0 slots and no spells are dropped. Sorted ascending by level.
     *
     * Each row: ['level' => int, 'ordinal' => string, 'slots' => int, 'spells' => array]
     *
     * @return array<int, array{level: int, ordinal: string, slots: int, spells: array}>
     */
    public function getSlotsWithSpellsAttribute(): array
    {
        if (! $this->isSpellcasting()) {
            return [];
        }

        $slotMap = $this->slots ?? [];
        $spellsByLevel = [];

        foreach ($this->spells_known_or_prepared ?? [] as $spell) {
            $level = $spell['level'] ?? null;
            if ($level === null || $level === '') {
                continue;
            }
            $spellsByLevel[(int) $level][] = $spell;
        }

        $allLevels = array_unique(array_merge(
            array_map('intval', array_keys($slotMap)),
            array_keys($spellsByLevel)
        ));
        sort($allLevels);

        $rows = [];
        foreach ($allLevels as $level) {
            $slotCount   = (int) ($slotMap[$level] ?? 0);
            $levelSpells = $spellsByLevel[$level] ?? [];

            if ($slotCount === 0 && empty($levelSpells)) {
                continue;
            }

            $rows[] = [
                'level'   => $level,
                'ordinal' => self::ordinalLevel($level),
                'slots'   => $slotCount,
                'spells'  => $levelSpells,
            ];
        }

        return $rows;
    }

    /**
     * Spells in spells_known_or_prepared that carry no level (or a null/blank one).
     * These are legacy entries saved before the level field was introduced.
     * Preserves original order. Empty array for non-Spellcasting profiles.
     *
     * @return array<int, array{library_id: string|null, name: string}>
     */
    public function getSpellsWithoutLevelAttribute(): array
    {
        if (! $this->isSpellcasting()) {
            return [];
        }

        return array_values(array_filter(
            $this->spells_known_or_prepared ?? [],
            fn (array $spell): bool => ($spell['level'] ?? null) === null || ($spell['level'] ?? '') === ''
        ));
    }

    /**
     * Format the spell slot table for Spellcasting statblock display.
     * Example: "1st level (4 slots), 2nd level (3 slots)"
     * Returns null for non-Spellcasting profiles or when slots are empty.
     */
    public function getFormattedSlotsLineAttribute(): ?string
    {
        if (! $this->isSpellcasting() || empty($this->slots)) {
            return null;
        }

        $parts = [];
        foreach ($this->slots as $level => $count) {
            if ((int) $count > 0) {
                $parts[] = self::ordinalLevel((int) $level) . " ({$count} slots)";
            }
        }

        return empty($parts) ? null : implode(', ', $parts);
    }

    /**
     * Format the Pact Magic slot block for statblock display.
     * Example: "2 3rd-level slots (recovers on a short or long rest)"
     * Returns null for non-PactMagic profiles or when slot data is absent.
     */
    public function getFormattedPactMagicLineAttribute(): ?string
    {
        if (! $this->isPactMagic() || is_null($this->slot_count) || is_null($this->slot_level)) {
            return null;
        }

        $count = $this->slot_count;
        $levelLabel = self::ordinalSuffix($this->slot_level);
        $slotWord = $count === 1 ? 'slot' : 'slots';

        return "{$count} {$levelLabel}-level {$slotWord} (recovers on a short or long rest)";
    }

    /**
     * Return an ordinal level string like "1st level", "2nd level", etc.
     */
    private static function ordinalLevel(int $level): string
    {
        return self::ordinalSuffix($level) . ' level';
    }

    /**
     * Return an ordinal suffix string for a given integer (1→1st, 2→2nd, etc.).
     * Public so views can reuse the same formatting instead of duplicating it.
     */
    public static function ordinalSuffix(int $n): string
    {
        return match ($n) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            default => "{$n}th",
        };
    }
}
