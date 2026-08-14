<?php

namespace Tests\Feature\Api\V1;

use App\Models\Npc;
use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
use App\Models\NpcSpellcasting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellcastingSerializationTest extends TestCase
{
    use RefreshDatabase;

    // ── Profiles win over legacy ─────────────────────────────────────────────

    /**
     * When casting profiles exist, they win over any legacy npc_spellcasting row.
     * The legacy row's data must not appear anywhere in the response.
     */
    public function test_profiles_win_over_legacy_when_both_exist(): void
    {
        $npc = Npc::factory()->create();

        // Legacy row
        NpcSpellcasting::factory()->for($npc)->create([
            'ability'           => 'Intelligence',
            'spell_save_dc'     => 13,
            'spell_attack_bonus' => 5,
        ]);

        // Profile row
        NpcCastingProfile::factory()->for($npc)->innate()->create([
            'spellcasting_ability' => 'Charisma',
            'save_dc'              => 15,
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $spellcasting = $response->json('data.spellcasting');

        $this->assertCount(1, $spellcasting, 'Profiles win — exactly one entry from the profile');
        $this->assertEquals('Innate', $spellcasting[0]['type']);
        $this->assertEquals('Charisma', $spellcasting[0]['ability']);
        $this->assertEquals(15, $spellcasting[0]['saveDc']);

        // Legacy's DC of 13 must not appear
        foreach ($spellcasting as $entry) {
            $this->assertNotEquals(13, $entry['saveDc'], 'Legacy DC must not appear when profiles exist');
        }
    }

    // ── Legacy synthesis ─────────────────────────────────────────────────────

    public function test_legacy_spellcasting_synthesizes_one_entry_when_no_profiles(): void
    {
        $npc = Npc::factory()->create();

        NpcSpellcasting::factory()->for($npc)->create([
            'ability'            => 'Wisdom',
            'spell_save_dc'      => 14,
            'spell_attack_bonus' => 6,
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $spellcasting = $response->json('data.spellcasting');
        $this->assertCount(1, $spellcasting);

        $entry = $spellcasting[0];
        $this->assertEquals('Spellcasting', $entry['type']);
        $this->assertEquals('Wisdom', $entry['ability']);
        $this->assertEquals(14, $entry['saveDc']);
        $this->assertEquals(6, $entry['attackBonus']);
        $this->assertFalse($entry['psionics']);
        $this->assertNull($entry['sourceClass']);
        $this->assertEquals([], $entry['slots']);
    }

    // ── Neither exists → empty array ─────────────────────────────────────────

    public function test_no_spellcasting_returns_empty_array_not_null(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $spellcasting = $response->json('data.spellcasting');
        $this->assertIsArray($spellcasting);
        $this->assertEmpty($spellcasting);
    }

    // ── Profiles sort_order ───────────────────────────────────────────────────

    public function test_profiles_serialize_in_sort_order(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->for($npc)->innate()->create([
            'spellcasting_ability' => 'Charisma',
            'save_dc'              => 15,
            'sort_order'           => 2,
        ]);

        NpcCastingProfile::factory()->for($npc)->spellcasting()->create([
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $spellcasting = $response->json('data.spellcasting');
        $this->assertCount(2, $spellcasting);
        $this->assertEquals('Spellcasting', $spellcasting[0]['type'], 'sort_order=1 entry should be first');
        $this->assertEquals('Innate', $spellcasting[1]['type'], 'sort_order=2 entry should be second');
    }

    // ── Profile type serialization ────────────────────────────────────────────

    public function test_innate_profile_serializes_correctly(): void
    {
        $npc = Npc::factory()->create();

        $profile = NpcCastingProfile::factory()->for($npc)->innate()->create([
            'spellcasting_ability' => 'Charisma',
            'save_dc'              => 14,
            'attack_bonus'         => null,
            'psionics'             => false,
        ]);

        NpcInnateSpellEntry::factory()->for($profile, 'castingProfile')->atWill()->create([
            'spell_name' => 'Detect Magic',
        ]);

        NpcInnateSpellEntry::factory()->for($profile, 'castingProfile')->perDay(1)->create([
            'spell_name' => 'Dominate Person',
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $entry = $response->json('data.spellcasting.0');
        $this->assertEquals('Innate', $entry['type']);
        $this->assertEquals('Charisma', $entry['ability']);
        $this->assertEquals(14, $entry['saveDc']);
        $this->assertFalse($entry['psionics']);
        $this->assertNull($entry['sourceClass']);
        $this->assertEquals([], $entry['slots']);

        // Lines from formatted_innate_lines accessor
        $this->assertNotEmpty($entry['lines']);
        $linesText = implode(' ', $entry['lines']);
        $this->assertStringContainsString('Detect Magic', $linesText);
        $this->assertStringContainsString('Dominate Person', $linesText);
    }

    public function test_spellcasting_profile_serializes_slots_correctly(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->for($npc)->spellcasting()->create([
            'caster_level' => 9,
            'slots'        => [1 => 4, 2 => 3, 3 => 3],
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Magic Missile', 'level' => 1],
                ['library_id' => null, 'name' => 'Fireball', 'level' => 3],
            ],
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $entry = $response->json('data.spellcasting.0');
        $this->assertEquals('Spellcasting', $entry['type']);
        $this->assertEquals(9, $entry['casterLevel']);

        $slots = $entry['slots'];
        $this->assertNotEmpty($slots);

        $level1 = collect($slots)->firstWhere('level', 1);
        $this->assertNotNull($level1);
        $this->assertEquals(4, $level1['count']);
        $this->assertContains('Magic Missile', $level1['spells']);
    }

    // ── Non-numeric legacy caster_level ──────────────────────────────────────

    /**
     * A legacy caster_level value like "9th" (a string ordinal) must produce:
     * - casterLevel: null  (not coerced to an int)
     * - notes: contains "9th" (the text must not be silently lost)
     */
    public function test_non_numeric_legacy_caster_level_produces_null_and_preserves_text(): void
    {
        $npc = Npc::factory()->create();

        NpcSpellcasting::factory()->for($npc)->create([
            'caster_level'      => '9th',
            'spellcasting_notes' => 'Additional notes.',
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $entry = $response->json('data.spellcasting.0');

        $this->assertNull($entry['casterLevel'], 'Non-numeric caster_level must emit null, not a coerced int');

        $notes = $entry['notes'] ?? '';
        $this->assertNotNull($notes, 'Notes must not be null when caster_level text is preserved');
        $this->assertStringContainsString('9th', $notes, 'The non-numeric caster_level text must be preserved in notes');
    }

    public function test_numeric_legacy_caster_level_parses_to_int(): void
    {
        $npc = Npc::factory()->create();

        NpcSpellcasting::factory()->for($npc)->create([
            'caster_level' => '9',
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $entry = $response->json('data.spellcasting.0');
        $this->assertEquals(9, $entry['casterLevel']);
    }
}
