<?php

namespace Tests\Feature;

use App\Models\Npc;
use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
use App\Models\NpcSpellcasting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpcCastingProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    // --- Shared payload helpers ---

    /** Minimum base NPC fields required to pass validation. */
    private function baseNpcPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Test Spellcaster',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 14,
            'wisdom' => 12,
            'charisma' => 16,
        ], $override);
    }

    private function innateProfilePayload(array $override = []): array
    {
        return array_merge([
            'casting_type' => 'Innate',
            'spellcasting_ability' => 'Charisma',
            'save_dc' => 14,
            'attack_bonus' => null,
            'psionics' => false,
            'source' => 'Monster Manual, Drow',
            'homebrew' => false,
            'race_or_origin' => 'Drow Magic',
        ], $override);
    }

    private function spellcastingProfilePayload(array $override = []): array
    {
        return array_merge([
            'casting_type' => 'Spellcasting',
            'spellcasting_ability' => 'Intelligence',
            'save_dc' => 15,
            'attack_bonus' => 7,
            'psionics' => false,
            'source' => 'Monster Manual, Archmage',
            'homebrew' => false,
            'caster_level' => 18,
            'source_class' => 'Wizard',
            'slots' => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
            'cantrips' => [['library_id' => null, 'name' => 'Fire Bolt']],
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'Magic Missile']],
        ], $override);
    }

    private function pactMagicProfilePayload(array $override = []): array
    {
        return array_merge([
            'casting_type' => 'PactMagic',
            'spellcasting_ability' => 'Charisma',
            'save_dc' => 14,
            'attack_bonus' => 6,
            'source' => 'Monster Manual, Warlock Patron',
            'homebrew' => false,
            'caster_level' => 5,
            'slot_level' => 3,
            'slot_count' => 2,
            'cantrips' => [['library_id' => null, 'name' => 'Eldritch Blast']],
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'Hex']],
        ], $override);
    }

    // --- Successful store tests ---

    public function test_store_persists_one_profile_of_each_casting_type(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->innateProfilePayload([
                    'innate_entries' => [
                        ['spell_name' => 'Dancing Lights', 'usage' => 'AtWill'],
                        ['spell_name' => 'Darkness', 'usage' => 'PerDay', 'uses_per_day' => 1],
                    ],
                ]),
                $this->spellcastingProfilePayload(),
                $this->pactMagicProfilePayload(),
            ],
        ]));

        $response->assertRedirect();

        $npc = Npc::where('name', 'Test Spellcaster')->firstOrFail();
        $profiles = NpcCastingProfile::where('npc_id', $npc->id)->get();
        $this->assertCount(3, $profiles);

        // Verify Innate profile
        $innate = $profiles->firstWhere('casting_type', 'Innate');
        $this->assertNotNull($innate);
        $this->assertSame('Drow Magic', $innate->race_or_origin);
        $this->assertSame('Monster Manual, Drow', $innate->source);

        $entries = NpcInnateSpellEntry::where('casting_profile_id', $innate->id)->get();
        $this->assertCount(2, $entries);
        $this->assertNotNull($entries->firstWhere('spell_name', 'Dancing Lights'));
        $this->assertNotNull($entries->firstWhere('spell_name', 'Darkness'));

        // Verify Spellcasting profile
        $spellcasting = $profiles->firstWhere('casting_type', 'Spellcasting');
        $this->assertNotNull($spellcasting);
        $this->assertSame('Wizard', $spellcasting->source_class);
        $this->assertSame(18, $spellcasting->caster_level);
        $this->assertIsArray($spellcasting->slots);
        $this->assertIsArray($spellcasting->cantrips);
        $this->assertIsArray($spellcasting->spells_known_or_prepared);

        // Verify PactMagic profile
        $pact = $profiles->firstWhere('casting_type', 'PactMagic');
        $this->assertNotNull($pact);
        $this->assertSame(3, $pact->slot_level);
        $this->assertSame(2, $pact->slot_count);
        $this->assertSame(5, $pact->caster_level);
    }

    // --- Validation failure tests ---

    public function test_store_rejects_per_day_innate_entry_without_uses_per_day(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->innateProfilePayload([
                    'innate_entries' => [
                        ['spell_name' => 'Darkness', 'usage' => 'PerDay'],
                    ],
                ]),
            ],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_rejects_at_will_innate_entry_with_uses_per_day_set(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->innateProfilePayload([
                    'innate_entries' => [
                        ['spell_name' => 'Dancing Lights', 'usage' => 'AtWill', 'uses_per_day' => 1],
                    ],
                ]),
            ],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_rejects_spellcasting_profile_missing_source_class(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->spellcastingProfilePayload(['source_class' => null]),
            ],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_rejects_pact_magic_profile_missing_slot_level(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->pactMagicProfilePayload(['slot_level' => null]),
            ],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_rejects_profile_missing_source(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->innateProfilePayload(['source' => '']),
            ],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_rejects_invalid_casting_type(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->innateProfilePayload(['casting_type' => 'InvalidType']),
            ],
        ]));

        $response->assertStatus(422);
    }

    // --- Sanitizer test ---

    public function test_sanitizer_nulls_inapplicable_fields_for_innate_profile(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->innateProfilePayload([
                    // These fields should not apply to Innate and must be silently nulled
                    'slots' => [1 => 4, 2 => 3],
                    'caster_level' => 5,
                    'source_class' => 'Wizard',
                ]),
            ],
        ]));

        // Sanitizer silently drops inapplicable fields; the request itself should succeed
        $response->assertRedirect();

        $npc = Npc::where('name', 'Test Spellcaster')->firstOrFail();
        $profile = NpcCastingProfile::where('npc_id', $npc->id)->firstOrFail();

        $this->assertNull($profile->slots);
        $this->assertNull($profile->caster_level);
        $this->assertNull($profile->source_class);
    }

    // --- Update / delete-recreate test ---

    public function test_update_replaces_casting_profiles_and_deletes_old_entries(): void
    {
        // Create NPC with 2 profiles (one Innate with entries)
        $npc = Npc::factory()->create(['name' => 'Updating Spellcaster']);
        $oldProfile = NpcCastingProfile::factory()->innate()->create(['npc_id' => $npc->id]);
        NpcInnateSpellEntry::factory()->atWill()->create(['casting_profile_id' => $oldProfile->id]);
        NpcInnateSpellEntry::factory()->perDay(1)->create(['casting_profile_id' => $oldProfile->id]);
        NpcCastingProfile::factory()->spellcasting()->create(['npc_id' => $npc->id]);

        $this->assertDatabaseCount('npc_casting_profiles', 2);
        $this->assertDatabaseCount('npc_innate_spell_entries', 2);

        // Update with a single new profile (no entries)
        $response = $this->put(route('npcs.update', $npc), $this->baseNpcPayload([
            'name' => 'Updating Spellcaster',
            'casting_profiles' => [
                $this->pactMagicProfilePayload(),
            ],
        ]));

        $response->assertRedirect();

        // Old profiles and their entries must be gone; only 1 new profile
        $this->assertDatabaseCount('npc_casting_profiles', 1);
        $this->assertDatabaseCount('npc_innate_spell_entries', 0);

        $remaining = NpcCastingProfile::where('npc_id', $npc->id)->firstOrFail();
        $this->assertSame('PactMagic', $remaining->casting_type);
    }

    public function test_update_without_casting_profiles_key_does_not_wipe_existing_profiles(): void
    {
        // Casting profiles created another way (API/seeder/factory) before the form knows
        // about this field — the current edit form never sends `casting_profiles` at all.
        $npc = Npc::factory()->create(['name' => 'Untouched By Form']);
        NpcCastingProfile::factory()->spellcasting()->create(['npc_id' => $npc->id]);

        $payload = $this->baseNpcPayload(['name' => 'Untouched By Form']);
        $this->assertArrayNotHasKey('casting_profiles', $payload);

        $response = $this->put(route('npcs.update', $npc), $payload);

        $response->assertRedirect();
        $this->assertDatabaseCount('npc_casting_profiles', 1);
    }

    public function test_sanitizer_nulls_psionics_for_pact_magic_profile(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->pactMagicProfilePayload(['psionics' => true]),
            ],
        ]));

        $response->assertRedirect();

        $profile = NpcCastingProfile::where('casting_type', 'PactMagic')->firstOrFail();
        $this->assertFalse((bool) $profile->psionics);
    }

    public function test_innate_entry_order_is_derived_from_submission_position(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'casting_profiles' => [
                $this->innateProfilePayload([
                    'innate_entries' => [
                        ['spell_name' => 'Fire Bolt', 'usage' => 'AtWill'],
                        ['spell_name' => 'Charm Person', 'usage' => 'AtWill'],
                        ['spell_name' => 'Darkness', 'usage' => 'AtWill'],
                    ],
                ]),
            ],
        ]));

        $response->assertRedirect();

        $profile = NpcCastingProfile::where('casting_type', 'Innate')->firstOrFail();
        $names = $profile->innateEntries()->orderBy('sort_order')->pluck('spell_name')->all();

        $this->assertSame(['Fire Bolt', 'Charm Person', 'Darkness'], $names);
    }

    // --- Duplicate test ---

    public function test_duplicate_via_controller_copies_casting_profiles_and_innate_entries(): void
    {
        $npc = Npc::factory()->create(['name' => 'Original Caster']);

        $innateProfile = NpcCastingProfile::factory()->innate()->create(['npc_id' => $npc->id]);
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $innateProfile->id,
            'spell_name' => 'Dancing Lights',
        ]);

        NpcCastingProfile::factory()->spellcasting()->create(['npc_id' => $npc->id]);

        $response = $this->post(route('npcs.duplicate', $npc));
        $response->assertRedirect();

        $cloneNpc = Npc::where('name', 'Original Caster (Copy)')->firstOrFail();

        // Both profiles cloned
        $cloneProfiles = NpcCastingProfile::where('npc_id', $cloneNpc->id)->get();
        $this->assertCount(2, $cloneProfiles);

        // IDs differ from original
        $originalProfileIds = NpcCastingProfile::where('npc_id', $npc->id)->pluck('id');
        foreach ($cloneProfiles as $cloneProfile) {
            $this->assertNotContains($cloneProfile->id, $originalProfileIds);
        }

        // Innate entries cloned
        $cloneInnate = $cloneProfiles->firstWhere('casting_type', 'Innate');
        $this->assertNotNull($cloneInnate);

        $cloneEntries = NpcInnateSpellEntry::where('casting_profile_id', $cloneInnate->id)->get();
        $this->assertCount(1, $cloneEntries);
        $this->assertSame('Dancing Lights', $cloneEntries->first()->spell_name);
    }

    // --- Legacy coexistence test ---

    public function test_store_persists_both_legacy_spellcasting_and_casting_profiles(): void
    {
        $response = $this->post(route('npcs.store'), $this->baseNpcPayload([
            'has_spellcasting' => true,
            'spellcasting' => [
                'ability' => 'Wisdom',
                'spell_save_dc' => 13,
                'spell_attack_bonus' => 5,
                'caster_level' => '6',  // legacy rule is nullable|string
                'spellcasting_notes' => 'Legacy spellcasting block.',
            ],
            'casting_profiles' => [
                $this->innateProfilePayload(),
            ],
        ]));

        $response->assertRedirect();

        $npc = Npc::where('name', 'Test Spellcaster')->firstOrFail();

        // Legacy spellcasting row persists
        $this->assertDatabaseHas('npc_spellcasting', [
            'npc_id' => $npc->id,
            'ability' => 'Wisdom',
        ]);

        // New casting profile also persists independently
        $this->assertDatabaseHas('npc_casting_profiles', [
            'npc_id' => $npc->id,
            'casting_type' => 'Innate',
        ]);
    }
}
