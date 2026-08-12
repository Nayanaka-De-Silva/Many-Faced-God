<?php

namespace Tests\Feature;

use App\Models\Npc;
use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4: Display-only tests for casting profiles on the NPC and template show pages.
 *
 * Covers:
 * - Innate profile (psionics, race_or_origin, at-will, per-day entries) renders on npcs.show.
 * - Spellcasting profile (caster level, source class, slot summary, cantrips, spells) renders.
 * - Pact Magic profile (pact magic line, spells) renders.
 * - Source citation and homebrew badge render when set.
 * - NPC with no casting profiles shows no profiles section; legacy spellcasting still renders.
 * - Spell refs with library_id render as clickable elements; refs without library_id render as plain text.
 * - Template show page also renders casting profiles (closes the known gap).
 */
class NpcCastingProfileDisplayTest extends TestCase
{
    use RefreshDatabase;

    // ── Innate profile ───────────────────────────────────────────────────────

    public function test_innate_profile_with_psionics_and_race_origin_renders_on_npc_show(): void
    {
        $npc = Npc::factory()->create(['name' => 'Mindflayer']);

        $profile = NpcCastingProfile::factory()->innate()->create([
            'npc_id'               => $npc->id,
            'psionics'             => true,
            'race_or_origin'       => 'Mind Flayer Psionics',
            'save_dc'              => 15,
            'spellcasting_ability' => 'Intelligence',
        ]);

        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name'         => 'Detect Thoughts',
            'spell_library_id'   => null,
        ]);

        NpcInnateSpellEntry::factory()->perDay(1)->create([
            'casting_profile_id' => $profile->id,
            'spell_name'         => 'Dominate Monster',
            'spell_library_id'   => null,
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Psionics');
        $response->assertSee('Mind Flayer Psionics');
        $response->assertSee('At will');
        $response->assertSee('Detect Thoughts');
        $response->assertSee('1/day each');
        $response->assertSee('Dominate Monster');
    }

    // ── Spellcasting profile ─────────────────────────────────────────────────

    public function test_spellcasting_profile_renders_on_npc_show(): void
    {
        $npc = Npc::factory()->create(['name' => 'Archmage']);

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'                   => $npc->id,
            'caster_level'             => 18,
            'source_class'             => 'Wizard',
            'save_dc'                  => 17,
            'attack_bonus'             => 9,
            'slots'                    => [1 => 4, 2 => 3],
            'cantrips'                 => [['library_id' => null, 'name' => 'Fire Bolt']],
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'Magic Missile']],
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('18th-level spellcaster');
        $response->assertSee('Wizard');
        $response->assertSee('1st level (4 slots)');
        $response->assertSee('Fire Bolt');
        $response->assertSee('Magic Missile');
    }

    public function test_spellcasting_profile_with_null_caster_level_renders_without_crashing(): void
    {
        // caster_level is required by form validation but is a nullable DB column — a
        // profile created another way (factory/seeder/direct write) with no caster_level
        // must degrade gracefully rather than 500 the whole show page.
        $npc = Npc::factory()->create(['name' => 'Mystery Caster']);

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id' => $npc->id,
            'caster_level' => null,
            'source_class' => 'Wizard',
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('is a spellcaster');
        $response->assertDontSee('th-level spellcaster');
    }

    // ── Pact Magic profile ───────────────────────────────────────────────────

    public function test_pact_magic_profile_renders_on_npc_show(): void
    {
        $npc = Npc::factory()->create(['name' => 'Warlock']);

        NpcCastingProfile::factory()->pactMagic()->create([
            'npc_id'                   => $npc->id,
            'slot_count'               => 2,
            'slot_level'               => 3,
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'Hex']],
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Pact Magic');
        $response->assertSee('2 3rd-level slots');
        $response->assertSee('short or long rest');
        $response->assertSee('Hex');
    }

    // ── Source citation ──────────────────────────────────────────────────────

    public function test_profile_source_renders_citation_on_npc_show(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->innate()->create([
            'npc_id' => $npc->id,
            'source' => 'Mordenkainen Presents p. 42',
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Mordenkainen Presents p. 42');
    }

    // ── Homebrew badge ───────────────────────────────────────────────────────

    public function test_homebrew_profile_renders_badge_on_npc_show(): void
    {
        $npc = Npc::factory()->create();

        NpcCastingProfile::factory()->innate()->create([
            'npc_id'   => $npc->id,
            'homebrew' => true,
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Homebrew');
    }

    // ── No casting profiles ──────────────────────────────────────────────────

    public function test_npc_with_no_casting_profiles_renders_no_profiles_section(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertDontSee('Innate Spellcasting');
        $response->assertDontSee('Pact Magic');
    }

    public function test_legacy_spellcasting_still_renders_when_no_casting_profiles(): void
    {
        $npc = Npc::factory()->create(['name' => 'Hedge Wizard']);

        $npc->spellcasting()->create([
            'ability'            => 'Wisdom',
            'spell_save_dc'      => 13,
            'spell_attack_bonus' => 5,
            'caster_level'       => '3rd',
            'spellcasting_notes' => null,
            'spells'             => null,
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Spellcasting (Description)');
        $response->assertSee('3rd');
        $response->assertSee('Wisdom');
    }

    // ── Clickable spell names ────────────────────────────────────────────────

    public function test_spell_with_library_id_renders_as_clickable_element(): void
    {
        $npc = Npc::factory()->create(['name' => 'Test Caster']);

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'                   => $npc->id,
            'cantrips'                 => [
                ['library_id' => 'lib-spell-fire-bolt', 'name' => 'Fire Bolt'],
            ],
            'spells_known_or_prepared' => [
                ['library_id' => null, 'name' => 'Mage Armor'],
            ],
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        // Fire Bolt has a library_id — must render a clickable element with the correct data attribute.
        $response->assertSee('data-spell-id="lib-spell-fire-bolt"', false);
        // Mage Armor has no library_id — no empty data-spell-id attribute should appear.
        $response->assertDontSee('data-spell-id=""', false);
    }

    public function test_innate_spell_with_library_id_renders_as_clickable_element(): void
    {
        $npc = Npc::factory()->create(['name' => 'Drow Mage']);

        $profile = NpcCastingProfile::factory()->innate()->create(['npc_id' => $npc->id]);

        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name'         => 'Dancing Lights',
            'spell_library_id'   => 'lib-dancing-lights',
        ]);

        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $profile->id,
            'spell_name'         => 'Darkness',
            'spell_library_id'   => null,
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        // Dancing Lights has a library_id — must render as a clickable element.
        $response->assertSee('data-spell-id="lib-dancing-lights"', false);
        // Darkness has no library_id — no empty data-spell-id attribute should appear.
        $response->assertDontSee('data-spell-id=""', false);
    }

    // ── Spell detail modal placement (issue #56) ─────────────────────────────

    /**
     * The spell modal must render outside the statblock card. Nested inside .npc-card its
     * position:fixed is re-anchored by the card's :hover transform, which makes the modal
     * flicker and vanish. The page content — and with it every .npc-card — lives inside the
     * layout's <main>, so the modal appearing after </main> is the invariant that matters.
     */
    public function test_spell_detail_modal_renders_outside_the_statblock_card(): void
    {
        $npc = Npc::factory()->create(['name' => 'Placement Check']);

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'   => $npc->id,
            'cantrips' => [
                ['library_id' => 'lib-spell-fire-bolt', 'name' => 'Fire Bolt'],
            ],
        ]);

        $this->assertSpellModalRendersAtBodyLevel(route('npcs.show', $npc));
    }

    /** The template show page wraps the same partial in .npc-card.template-card, which shares
     *  the hover transform — so it needs the placement pinned independently. */
    public function test_spell_detail_modal_renders_outside_the_statblock_card_on_templates_show(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Placement Check Template']);

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'   => $template->id,
            'cantrips' => [
                ['library_id' => 'lib-spell-fire-bolt', 'name' => 'Fire Bolt'],
            ],
        ]);

        $this->assertSpellModalRendersAtBodyLevel(route('templates.show', $template));
    }

    private function assertSpellModalRendersAtBodyLevel(string $url): void
    {
        $html = $this->get($url)->assertStatus(200)->getContent();

        $modalPosition      = strpos($html, 'id="spellDetailModal"');
        $contentEndPosition = strrpos($html, '</main>');

        $this->assertNotFalse($modalPosition, 'The spell detail modal should be rendered.');
        $this->assertNotFalse($contentEndPosition, 'The layout should wrap page content in <main>.');
        $this->assertGreaterThan(
            $contentEndPosition,
            $modalPosition,
            'The spell detail modal must render at body level, not inside the .npc-card statblock.'
        );

        // The modal is shared by every spell link, so exactly one copy should exist.
        $this->assertSame(1, substr_count($html, 'id="spellDetailModal"'));
    }

    // ── Template show page ───────────────────────────────────────────────────

    public function test_template_with_casting_profile_renders_on_templates_show(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Lich']);

        NpcCastingProfile::factory()->spellcasting()->create([
            'npc_id'       => $template->id,
            'source_class' => 'Wizard',
            'caster_level' => 18,
        ]);

        $response = $this->get(route('templates.show', $template));

        $response->assertStatus(200);
        $response->assertSee('Spellcasting');
        $response->assertSee('Wizard');
        $response->assertSee('18th-level spellcaster');
    }
}
