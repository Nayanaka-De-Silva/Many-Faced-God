<?php

namespace Tests\Feature;

use App\Models\Npc;
use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 UI regression tests for the Casting Profiles form section.
 *
 * Covers:
 * - The section renders on the create form.
 * - Saved profile data re-populates correctly on the edit form.
 * - A 422 validation failure re-renders the create form with the
 *   user's submitted (but invalid) data intact — the critical data-loss guard.
 * - The relabelled legacy spellcasting block is still present and still
 *   round-trips its field names without regression.
 */
class NpcCastingProfileFormTest extends TestCase
{
    use RefreshDatabase;

    // ── Create form ──────────────────────────────────────────────────────────

    public function test_create_form_renders_casting_profiles_section(): void
    {
        $response = $this->get(route('npcs.create'));

        $response->assertStatus(200);

        // The "add" button and the container must be present.
        $response->assertSee('Add Casting Profile');
        $response->assertSee('castingProfilesContainer', false);

        // The spell picker modal must be present.
        $response->assertSee('spellPickerModal', false);

        // Always-present marker, even with zero profile cards — lets the controller tell
        // "submitted with zero profiles" apart from "this request doesn't know the field".
        $response->assertSee('name="casting_profiles_submitted"', false);
    }

    public function test_removing_all_profile_cards_and_saving_actually_clears_them(): void
    {
        // Casting profiles exist; the form is submitted with the marker present but the
        // casting_profiles array empty — exactly what happens when a user removes every
        // profile card client-side and saves (an HTML form never sends an empty array key).
        $npc = Npc::factory()->create(['name' => 'Clearing Profiles']);
        NpcCastingProfile::factory()->spellcasting()->create(['npc_id' => $npc->id]);

        $response = $this->put(route('npcs.update', $npc), [
            'name' => 'Clearing Profiles',
            'strength' => 10, 'dexterity' => 10, 'constitution' => 10,
            'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10,
            'casting_profiles_submitted' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('npc_casting_profiles', 0);
    }

    // ── Edit form: saved data population ────────────────────────────────────

    public function test_edit_form_renders_saved_innate_profile_data(): void
    {
        $npc = Npc::factory()->create();

        $innate = NpcCastingProfile::factory()->innate()->create([
            'npc_id'         => $npc->id,
            'source'         => 'Monster Manual, Drow High Priestess',
            'race_or_origin' => 'Drow Heritage Unique',
        ]);

        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $innate->id,
            'spell_name'         => 'DancingLightsTestValue',
        ]);

        NpcInnateSpellEntry::factory()->perDay(3)->create([
            'casting_profile_id' => $innate->id,
            'spell_name'         => 'DarknessTestValue',
        ]);

        $response = $this->get(route('npcs.edit', $npc));

        $response->assertStatus(200);
        $response->assertSee('Monster Manual, Drow High Priestess');
        $response->assertSee('Drow Heritage Unique');
        $response->assertSee('DancingLightsTestValue');
        $response->assertSee('DarknessTestValue');
    }

    public function test_edit_form_renders_saved_spellcasting_profile_data(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'       => $npc->id,
            'source'       => 'Monster Manual, Archmage Unique',
            'source_class' => 'WizardTestClass',
            'caster_level' => 18,
        ]);

        $response = $this->get(route('npcs.edit', $npc));

        $response->assertStatus(200);
        $response->assertSee('Monster Manual, Archmage Unique');
        $response->assertSee('WizardTestClass');
    }

    public function test_edit_form_renders_saved_pact_magic_profile_data(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->pactMagic()->create([
            'npc_id'  => $npc->id,
            'source'  => 'Monster Manual, Warlock Patron Unique',
        ]);

        $response = $this->get(route('npcs.edit', $npc));

        $response->assertStatus(200);
        $response->assertSee('Monster Manual, Warlock Patron Unique');
    }

    public function test_edit_form_renders_saved_cantrips_and_spells_for_spellcasting_profile(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'                   => $npc->id,
            'cantrips'                 => [['library_id' => null, 'name' => 'FireBoltTestCantrip']],
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'MagicMissileTestSpell']],
        ]);

        $response = $this->get(route('npcs.edit', $npc));

        $response->assertStatus(200);
        $response->assertSee('FireBoltTestCantrip');
        $response->assertSee('MagicMissileTestSpell');
    }

    public function test_edit_form_renders_level_select_for_spell_rows_but_not_cantrip_rows(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'                   => $npc->id,
            'cantrips'                 => [['library_id' => null, 'name' => 'LevelSelectCantripTest']],
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'LevelSelectSpellTest', 'level' => 3]],
        ]);

        $response = $this->get(route('npcs.edit', $npc));

        $response->assertStatus(200);
        // Level select must appear for spell rows (with the saved level pre-selected)
        $response->assertSee('spells_known_or_prepared][0][level]', false);
        $response->assertSee('<option value="3" selected', false);
        // Level select must NOT appear for cantrip rows
        $response->assertDontSee('cantrips][0][level]', false);
    }

    // ── 422 re-render: data preservation ────────────────────────────────────

    /**
     * Submitting a profile with an empty source (required field) must trigger a 422.
     * The re-rendered form must contain the other submitted data the user typed —
     * nothing the user entered should be silently dropped.
     */
    public function test_422_rerender_preserves_submitted_casting_profile_data(): void
    {
        $payload = [
            'name'             => 'ValidationTestNpc',
            'strength'         => 10,
            'dexterity'        => 10,
            'constitution'     => 10,
            'intelligence'     => 10,
            'wisdom'           => 10,
            'charisma'         => 10,
            'casting_profiles' => [
                [
                    'casting_type'         => 'Innate',
                    'spellcasting_ability' => 'Charisma',
                    'save_dc'              => 14,
                    'source'               => '',   // empty — triggers required validation failure
                    'race_or_origin'       => 'DragonbornHeritageUniqueTest99',
                    'homebrew'             => '0',
                    'psionics'             => '0',
                ],
            ],
        ];

        $response = $this->post(route('npcs.store'), $payload);

        $response->assertStatus(422);

        // The submitted race_or_origin value must appear in the re-rendered HTML.
        $response->assertSee('DragonbornHeritageUniqueTest99');
    }

    public function test_422_rerender_preserves_innate_entry_spell_names(): void
    {
        $payload = [
            'name'             => 'ValidationTestNpc2',
            'strength'         => 10,
            'dexterity'        => 10,
            'constitution'     => 10,
            'intelligence'     => 10,
            'wisdom'           => 10,
            'charisma'         => 10,
            'casting_profiles' => [
                [
                    'casting_type'         => 'Innate',
                    'spellcasting_ability' => 'Charisma',
                    'save_dc'              => 14,
                    'source'               => 'Valid Source',
                    'homebrew'             => '0',
                    'psionics'             => '0',
                    'innate_entries'       => [
                        [
                            'spell_name' => 'UniqueDancingLightsTest88',
                            'usage'      => 'PerDay',
                            // uses_per_day omitted — triggers conditional validation failure
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->post(route('npcs.store'), $payload);

        $response->assertStatus(422);

        // The submitted spell name must survive the re-render.
        $response->assertSee('UniqueDancingLightsTest88');
    }

    // ── Legacy spellcasting section regression ───────────────────────────────

    public function test_legacy_spellcasting_section_is_present_after_relabeling(): void
    {
        $response = $this->get(route('npcs.create'));

        $response->assertStatus(200);

        // The new label must be visible.
        $response->assertSee('Spellcasting (Description)');

        // The legacy field names must still be present so existing data submits correctly.
        $response->assertSee('has_spellcasting', false);
        $response->assertSee('spellcasting[ability]', false);
        $response->assertSee('spellcasting[spell_save_dc]', false);
    }

    public function test_legacy_spellcasting_data_round_trips_on_edit(): void
    {
        $npc = Npc::factory()->create();

        $npc->spellcasting()->create([
            'ability'             => 'Wisdom',
            'spell_save_dc'       => 13,
            'spell_attack_bonus'  => 5,
            'caster_level'        => '6th',
            'spellcasting_notes'  => 'LegacyNotesUniqueTestValue',
        ]);

        $response = $this->get(route('npcs.edit', $npc));

        $response->assertStatus(200);
        $response->assertSee('LegacyNotesUniqueTestValue');
    }
}
