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
}
