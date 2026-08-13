<?php

namespace Tests\Unit;

use App\Models\Npc;
use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpcCastingProfileTest extends TestCase
{
    use RefreshDatabase;

    // --- Constants / maps (no DB needed) ---

    public function test_type_constants_are_correct(): void
    {
        $this->assertSame('Innate', NpcCastingProfile::TYPE_INNATE);
        $this->assertSame('Spellcasting', NpcCastingProfile::TYPE_SPELLCASTING);
        $this->assertSame('PactMagic', NpcCastingProfile::TYPE_PACT_MAGIC);
    }

    public function test_casting_types_map_has_all_three_types(): void
    {
        $types = NpcCastingProfile::CASTING_TYPES;

        $this->assertArrayHasKey(NpcCastingProfile::TYPE_INNATE, $types);
        $this->assertArrayHasKey(NpcCastingProfile::TYPE_SPELLCASTING, $types);
        $this->assertArrayHasKey(NpcCastingProfile::TYPE_PACT_MAGIC, $types);
    }

    public function test_abilities_constant_contains_required_values(): void
    {
        $this->assertContains('Intelligence', NpcCastingProfile::ABILITIES);
        $this->assertContains('Wisdom', NpcCastingProfile::ABILITIES);
        $this->assertContains('Charisma', NpcCastingProfile::ABILITIES);
        $this->assertCount(3, NpcCastingProfile::ABILITIES);
    }

    public function test_fields_by_type_covers_all_casting_types(): void
    {
        $this->assertArrayHasKey(NpcCastingProfile::TYPE_INNATE, NpcCastingProfile::FIELDS_BY_TYPE);
        $this->assertArrayHasKey(NpcCastingProfile::TYPE_SPELLCASTING, NpcCastingProfile::FIELDS_BY_TYPE);
        $this->assertArrayHasKey(NpcCastingProfile::TYPE_PACT_MAGIC, NpcCastingProfile::FIELDS_BY_TYPE);
    }

    public function test_fields_by_type_innate_excludes_slot_and_spellcasting_columns(): void
    {
        $innateNullFields = NpcCastingProfile::FIELDS_BY_TYPE[NpcCastingProfile::TYPE_INNATE];

        $this->assertContains('slots', $innateNullFields);
        $this->assertContains('caster_level', $innateNullFields);
        $this->assertContains('source_class', $innateNullFields);
        $this->assertContains('slot_level', $innateNullFields);
        $this->assertContains('slot_count', $innateNullFields);
        $this->assertContains('spells_known_or_prepared', $innateNullFields);
    }

    public function test_fields_by_type_spellcasting_excludes_pact_magic_columns(): void
    {
        $spellcastingNullFields = NpcCastingProfile::FIELDS_BY_TYPE[NpcCastingProfile::TYPE_SPELLCASTING];

        $this->assertContains('slot_level', $spellcastingNullFields);
        $this->assertContains('slot_count', $spellcastingNullFields);
        $this->assertContains('race_or_origin', $spellcastingNullFields);
    }

    public function test_fields_by_type_pact_magic_excludes_spellcasting_columns(): void
    {
        $pactNullFields = NpcCastingProfile::FIELDS_BY_TYPE[NpcCastingProfile::TYPE_PACT_MAGIC];

        $this->assertContains('slots', $pactNullFields);
        $this->assertContains('source_class', $pactNullFields);
        $this->assertContains('race_or_origin', $pactNullFields);
    }

    // --- Type helpers ---

    public function test_is_innate_returns_true_for_innate_type(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->make();

        $this->assertTrue($profile->isInnate());
        $this->assertFalse($profile->isSpellcasting());
        $this->assertFalse($profile->isPactMagic());
    }

    public function test_is_spellcasting_returns_true_for_spellcasting_type(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->make();

        $this->assertTrue($profile->isSpellcasting());
        $this->assertFalse($profile->isInnate());
        $this->assertFalse($profile->isPactMagic());
    }

    public function test_is_pact_magic_returns_true_for_pact_magic_type(): void
    {
        $profile = NpcCastingProfile::factory()->pactMagic()->make();

        $this->assertTrue($profile->isPactMagic());
        $this->assertFalse($profile->isInnate());
        $this->assertFalse($profile->isSpellcasting());
    }

    // --- Recovery accessor ---

    public function test_recovery_attribute_returns_short_or_long_rest_for_pact_magic(): void
    {
        $profile = NpcCastingProfile::factory()->pactMagic()->make();

        $this->assertSame('ShortOrLongRest', $profile->recovery);
    }

    public function test_recovery_attribute_returns_null_for_innate(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->make();

        $this->assertNull($profile->recovery);
    }

    public function test_recovery_attribute_returns_null_for_spellcasting(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->make();

        $this->assertNull($profile->recovery);
    }

    // --- Casts ---

    public function test_slots_attribute_casts_to_array(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create();

        $this->assertIsArray($profile->slots);
    }

    public function test_psionics_attribute_casts_to_bool(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create(['psionics' => true]);

        $this->assertIsBool($profile->psionics);
        $this->assertTrue($profile->psionics);
    }

    public function test_cantrips_attribute_casts_to_array_when_set(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create();

        $this->assertIsArray($profile->cantrips);
    }

    public function test_spells_known_or_prepared_attribute_casts_to_array_when_set(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create();

        $this->assertIsArray($profile->spells_known_or_prepared);
    }

    // --- Formatted innate lines ---

    public function test_formatted_innate_lines_groups_at_will_entries(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create();
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Dancing Lights',
            'sort_order' => 0,
        ]);
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Darkness',
            'sort_order' => 1,
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);
        $lines = $freshProfile->formatted_innate_lines;

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('At will:', $lines[0]);
        $this->assertStringContainsString('Dancing Lights', $lines[0]);
        $this->assertStringContainsString('Darkness', $lines[0]);
    }

    public function test_formatted_innate_lines_groups_per_day_entries_by_count(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create();
        NpcInnateSpellEntry::factory()->perDay(1)->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Darkness',
        ]);
        NpcInnateSpellEntry::factory()->perDay(3)->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Faerie Fire',
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);
        $lines = $freshProfile->formatted_innate_lines;

        $this->assertCount(2, $lines);

        $combinedLines = implode("\n", $lines);
        $this->assertStringContainsString('1/day each:', $combinedLines);
        $this->assertStringContainsString('Darkness', $combinedLines);
        $this->assertStringContainsString('3/day each:', $combinedLines);
        $this->assertStringContainsString('Faerie Fire', $combinedLines);
    }

    public function test_formatted_innate_lines_order_at_will_first_then_descending_uses(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create();
        NpcInnateSpellEntry::factory()->perDay(1)->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Darkness',
        ]);
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Dancing Lights',
        ]);
        NpcInnateSpellEntry::factory()->perDay(3)->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Faerie Fire',
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);
        $lines = $freshProfile->formatted_innate_lines;

        $this->assertCount(3, $lines);
        $this->assertStringStartsWith('At will:', $lines[0]);
        $this->assertStringStartsWith('3/day each:', $lines[1]);
        $this->assertStringStartsWith('1/day each:', $lines[2]);
    }

    public function test_formatted_innate_lines_treats_null_uses_per_day_as_one(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create();
        NpcInnateSpellEntry::factory()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Darkness',
            'usage' => NpcInnateSpellEntry::USAGE_PER_DAY,
            'uses_per_day' => null,
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);
        $lines = $freshProfile->formatted_innate_lines;

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('1/day each:', $lines[0]);
        $this->assertStringContainsString('Darkness', $lines[0]);
    }

    // --- Innate entry grouping helpers ---

    public function test_at_will_entries_returns_only_at_will_entries_in_sort_order(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create();
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Darkness',
            'sort_order' => 1,
        ]);
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Dancing Lights',
            'sort_order' => 0,
        ]);
        NpcInnateSpellEntry::factory()->perDay(2)->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Faerie Fire',
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);

        $this->assertSame(
            ['Dancing Lights', 'Darkness'],
            $freshProfile->innateAtWillSpells()->pluck('spell_name')->all()
        );
    }

    public function test_per_day_entry_groups_are_keyed_by_uses_and_ordered_descending(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create();
        foreach ([1, 3, 2] as $usesPerDay) {
            NpcInnateSpellEntry::factory()->perDay($usesPerDay)->create([
                'casting_profile_id' => $profile->id,
                'spell_name' => "Spell {$usesPerDay}",
            ]);
        }
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Dancing Lights',
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);
        $groups = $freshProfile->innatePerDaySpellGroups();

        $this->assertSame([3, 2, 1], $groups->keys()->all());
        $this->assertSame('Spell 3', $groups->get(3)->first()->spell_name);
    }

    public function test_innate_entry_group_helpers_are_empty_for_non_innate_profiles(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create();

        $this->assertTrue($profile->innateAtWillSpells()->isEmpty());
        $this->assertTrue($profile->innatePerDaySpellGroups()->isEmpty());
    }

    public function test_per_day_entry_groups_catches_unexpected_usage_values(): void
    {
        // An entry with a usage value that is neither AtWill nor PerDay (a data
        // error, or a future usage kind) must still surface in the statblock
        // rather than silently vanishing.
        $profile = NpcCastingProfile::factory()->innate()->create();
        NpcInnateSpellEntry::factory()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Mystery Spell',
            'usage' => 'Recharge',
            'uses_per_day' => 1,
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);
        $groups = $freshProfile->innatePerDaySpellGroups();

        $this->assertSame(['Mystery Spell'], $groups->get(1)->pluck('spell_name')->all());
    }

    public function test_formatted_innate_lines_includes_restriction(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->create();
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name' => 'Charm Person',
            'restriction' => 'self only',
        ]);

        $freshProfile = NpcCastingProfile::find($profile->id);
        $lines = $freshProfile->formatted_innate_lines;

        $this->assertStringContainsString('Charm Person (self only)', $lines[0]);
    }

    public function test_formatted_innate_lines_returns_empty_array_for_non_innate(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create();

        $this->assertSame([], $profile->formatted_innate_lines);
    }

    // --- Formatted slots line ---

    public function test_formatted_slots_line_returns_formatted_string_for_spellcasting(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create([
            'slots' => [1 => 4, 2 => 3],
        ]);

        $line = $profile->formatted_slots_line;

        $this->assertNotNull($line);
        $this->assertStringContainsString('1st level (4 slots)', $line);
        $this->assertStringContainsString('2nd level (3 slots)', $line);
    }

    public function test_formatted_slots_line_returns_null_for_innate(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->make();

        $this->assertNull($profile->formatted_slots_line);
    }

    public function test_formatted_slots_line_returns_null_for_pact_magic(): void
    {
        $profile = NpcCastingProfile::factory()->pactMagic()->make();

        $this->assertNull($profile->formatted_slots_line);
    }

    // --- Formatted pact magic line ---

    public function test_formatted_pact_magic_line_returns_formatted_string(): void
    {
        $profile = NpcCastingProfile::factory()->pactMagic()->create([
            'slot_count' => 2,
            'slot_level' => 3,
        ]);

        $line = $profile->formatted_pact_magic_line;

        $this->assertNotNull($line);
        $this->assertStringContainsString('2', $line);
        $this->assertStringContainsString('3rd', $line);
        $this->assertStringContainsString('short or long rest', $line);
    }

    public function test_formatted_pact_magic_line_returns_null_for_innate(): void
    {
        $profile = NpcCastingProfile::factory()->innate()->make();

        $this->assertNull($profile->formatted_pact_magic_line);
    }

    public function test_formatted_pact_magic_line_returns_null_for_spellcasting(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->make();

        $this->assertNull($profile->formatted_pact_magic_line);
    }

    // --- Slots with spells ---

    public function test_slots_with_spells_returns_empty_array_for_non_spellcasting(): void
    {
        $innate = NpcCastingProfile::factory()->innate()->make();
        $this->assertSame([], $innate->slots_with_spells);

        $pact = NpcCastingProfile::factory()->pactMagic()->make();
        $this->assertSame([], $pact->slots_with_spells);
    }

    public function test_slots_with_spells_groups_leveled_spells_by_level_sorted_ascending(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create([
            'slots' => [1 => 4, 2 => 3],
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Magic Missile', 'level' => 1],
                ['library_id' => null, 'name' => 'Shield', 'level' => 1],
                ['library_id' => null, 'name' => 'Misty Step', 'level' => 2],
            ],
        ]);

        $groups = $profile->slots_with_spells;

        $this->assertCount(2, $groups);
        $this->assertSame(1, $groups[0]['level']);
        $this->assertSame('1st level', $groups[0]['ordinal']);
        $this->assertSame(4, $groups[0]['slots']);
        $this->assertCount(2, $groups[0]['spells']);
        $this->assertSame('Magic Missile', $groups[0]['spells'][0]['name']);
        $this->assertSame(2, $groups[1]['level']);
        $this->assertSame('2nd level', $groups[1]['ordinal']);
        $this->assertSame(3, $groups[1]['slots']);
        $this->assertCount(1, $groups[1]['spells']);
    }

    public function test_slots_with_spells_includes_slot_levels_with_no_assigned_spells(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create([
            'slots' => [1 => 4, 2 => 3],
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Magic Missile', 'level' => 1],
                // No level-2 spell assigned
            ],
        ]);

        $groups = $profile->slots_with_spells;

        // Level 2 has slots but no spells — must not be dropped
        $this->assertCount(2, $groups);
        $level2 = collect($groups)->firstWhere('level', 2);
        $this->assertNotNull($level2);
        $this->assertSame(3, $level2['slots']);
        $this->assertSame([], $level2['spells']);
    }

    public function test_slots_with_spells_drops_rows_with_zero_slots_and_no_spells(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create([
            'slots' => [1 => 4, 2 => 0],
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Magic Missile', 'level' => 1],
            ],
        ]);

        $groups = $profile->slots_with_spells;

        // Level 2 has 0 slots and no spells — must be dropped
        $this->assertCount(1, $groups);
        $this->assertSame(1, $groups[0]['level']);
    }

    public function test_slots_with_spells_sorts_levels_ascending_regardless_of_slot_key_order(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->create([
            'slots' => [3 => 2, 1 => 4, 2 => 3],
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Fireball', 'level' => 3],
                ['library_id' => null, 'name' => 'Magic Missile', 'level' => 1],
            ],
        ]);

        $groups = $profile->slots_with_spells;

        $this->assertSame([1, 2, 3], array_column($groups, 'level'));
    }

    // --- Spells without level ---

    public function test_spells_without_level_returns_empty_array_for_non_spellcasting(): void
    {
        $innate = NpcCastingProfile::factory()->innate()->make();
        $this->assertSame([], $innate->spells_without_level);

        $pact = NpcCastingProfile::factory()->pactMagic()->make();
        $this->assertSame([], $pact->spells_without_level);
    }

    public function test_spells_without_level_returns_entries_with_no_level_key(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->make([
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Magic Missile'],           // no level key
                ['library_id' => null, 'name' => 'Shield', 'level' => 1],   // has level — excluded
                ['library_id' => null, 'name' => 'Mage Armor', 'level' => null], // null level — included
            ],
        ]);

        $unlevel = $profile->spells_without_level;

        $this->assertCount(2, $unlevel);
        $this->assertSame('Magic Missile', $unlevel[0]['name']);
        $this->assertSame('Mage Armor', $unlevel[1]['name']);
    }

    public function test_spells_without_level_excludes_spells_with_a_level_value(): void
    {
        $profile = NpcCastingProfile::factory()->spellcasting()->make([
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Magic Missile', 'level' => 1],
            ],
        ]);

        $this->assertSame([], $profile->spells_without_level);
    }
}
