<?php

namespace Tests\Feature\Api\V1;

use App\Models\Folder;
use App\Models\Npc;
use App\Models\NpcCastingProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NpcApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Envelope shape ──────────────────────────────────────────────────────

    public function test_index_returns_200_with_correct_envelope_shape(): void
    {
        Npc::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/npcs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['page', 'pageSize', 'totalItems', 'totalPages'],
            ]);
    }

    /**
     * Laravel's default paginator wrapper keys must never appear — they would
     * break Arena's contract with the envelope spec.
     */
    public function test_index_does_not_leak_laravel_paginator_keys(): void
    {
        Npc::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/npcs');
        $response->assertStatus(200);

        $body = $response->json();

        $this->assertArrayNotHasKey('per_page', $body);
        $this->assertArrayNotHasKey('current_page', $body);
        $this->assertArrayNotHasKey('total', $body);
        $this->assertArrayNotHasKey('links', $body);
    }

    public function test_npc_keys_are_camel_case_not_snake_case(): void
    {
        Npc::factory()->create();

        $response = $this->getJson('/api/v1/npcs');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $npc = $data[0];
        $snakeCasePattern = '/_[a-z]/';

        foreach (array_keys($npc) as $key) {
            $this->assertDoesNotMatchRegularExpression(
                $snakeCasePattern,
                $key,
                "Key '$key' appears to be snake_case; all NPC resource keys must be camelCase."
            );
        }

        // Spot-check expected camelCase keys are present
        $this->assertArrayHasKey('sourceRef', $npc);
        $this->assertArrayHasKey('isTemplate', $npc);
        $this->assertArrayHasKey('armorClass', $npc);
        $this->assertArrayHasKey('hitPoints', $npc);
        $this->assertArrayHasKey('hitDice', $npc);
        $this->assertArrayHasKey('challengeRating', $npc);
        $this->assertArrayHasKey('proficiencyBonus', $npc);
        $this->assertArrayHasKey('savingThrowProficiencies', $npc);
        $this->assertArrayHasKey('skillProficiencies', $npc);
        $this->assertArrayHasKey('passivePerception', $npc);
        $this->assertArrayHasKey('damageVulnerabilities', $npc);
        $this->assertArrayHasKey('damageResistances', $npc);
        $this->assertArrayHasKey('damageImmunities', $npc);
        $this->assertArrayHasKey('conditionImmunities', $npc);
        $this->assertArrayHasKey('personalityTraits', $npc);
        $this->assertArrayHasKey('createdAt', $npc);
        $this->assertArrayHasKey('updatedAt', $npc);
    }

    // ── Templates excluded from /npcs ───────────────────────────────────────

    public function test_index_excludes_templates(): void
    {
        Npc::factory()->count(2)->create();
        Npc::factory()->template()->count(3)->create();

        $response = $this->getJson('/api/v1/npcs');
        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.totalItems'));

        foreach ($response->json('data') as $npc) {
            $this->assertFalse($npc['isTemplate']);
        }
    }

    public function test_show_returns_single_npc(): void
    {
        $npc = Npc::factory()->create(['name' => 'Grizzik']);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $npc->id)
            ->assertJsonPath('data.name', 'Grizzik');
    }

    /**
     * A template id passed to /npcs/{id} must 404 — it exists in the table
     * but is out of scope for the NPC endpoint (is_template = false only).
     * This is the correct 404, not a bug.
     */
    public function test_show_returns_404_for_template_id(): void
    {
        $template = Npc::factory()->template()->create();

        $response = $this->getJson("/api/v1/npcs/{$template->id}");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_show_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/v1/npcs/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * The {npc} route param is constrained to digits. Without that
     * constraint, a non-numeric segment reaches show(int $npc) and PHP's
     * weak-mode int coercion throws an uncaught TypeError on a non-numeric
     * string, yielding a 500 instead of the documented 404.
     */
    public function test_show_returns_404_not_500_for_non_numeric_id(): void
    {
        $response = $this->getJson('/api/v1/npcs/abc');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * ?sort[]=name is a malformed shape for a param that's normally a
     * scalar string. Without a scalar guard, array_key_exists() below
     * receives an array as the key argument and throws an uncaught
     * TypeError, leaking a raw PHP error message via the generic 500
     * handler instead of behaving like any other unrecognised param.
     */
    public function test_array_shaped_sort_param_is_ignored_not_500(): void
    {
        Npc::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/npcs?sort[]=name&sort[]=hitPoints');

        $response->assertStatus(200);
    }

    // ── sourceRef ────────────────────────────────────────────────────────────

    public function test_source_ref_follows_mfg_npc_id_format(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.sourceRef', "mfg:npc:{$npc->id}");
    }

    // ── challengeRating object ───────────────────────────────────────────────

    /**
     * Load-bearing correctness test for the whole feature.
     * A null challenge_rating must propagate as null xp and null xpIfDangerous —
     * never 0. Coercing null to 0 corrupts every DMG difficulty calc Arena runs.
     */
    public function test_null_challenge_rating_emits_null_xp_never_zero(): void
    {
        $npc = Npc::factory()->create(['challenge_rating' => null]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $cr = $response->json('data.challengeRating');
        $this->assertIsArray($cr, 'challengeRating must always be an object, never bare null');
        $this->assertNull($cr['value'], 'value must be null');
        $this->assertNull($cr['xp'], 'xp must be null when CR is null, never 0');
        $this->assertNull($cr['xpIfDangerous'], 'xpIfDangerous must be null when CR is not "0"');
    }

    public function test_challenge_rating_zero_includes_xp_if_dangerous(): void
    {
        $npc = Npc::factory()->create(['challenge_rating' => '0']);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $cr = $response->json('data.challengeRating');
        $this->assertEquals('0', $cr['value']);
        $this->assertEquals(0, $cr['xp']);
        $this->assertEquals(10, $cr['xpIfDangerous'], 'xpIfDangerous must be 10 (XP_CR_ZERO_IF_DANGEROUS) for CR 0');
    }

    public function test_challenge_rating_non_zero_has_null_xp_if_dangerous(): void
    {
        $npc = Npc::factory()->create(['challenge_rating' => '5']);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $cr = $response->json('data.challengeRating');
        $this->assertEquals('5', $cr['value']);
        $this->assertEquals(1800, $cr['xp']);
        $this->assertNull($cr['xpIfDangerous']);
    }

    // ── proficiencyBonus ─────────────────────────────────────────────────────

    public function test_proficiency_bonus_is_stored_column_not_computed(): void
    {
        // A DM hand-set proficiency_bonus of 5 on a CR 1 NPC — must see 5, not 2.
        $npc = Npc::factory()->create(['challenge_rating' => '1', 'proficiency_bonus' => 5]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $this->assertEquals(5, $response->json('data.proficiencyBonus'));
    }

    // ── hitPointsSuggested ───────────────────────────────────────────────────

    public function test_hit_points_suggested_is_null_when_hit_points_is_set(): void
    {
        $npc = Npc::factory()->create(['hit_points' => 27, 'hit_dice' => '5d8+5']);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $this->assertNull($response->json('data.hitPointsSuggested'));
    }

    public function test_hit_points_suggested_is_deterministic_when_hit_points_is_null(): void
    {
        $npc = Npc::factory()->create(['hit_points' => null, 'hit_dice' => '5d8+5']);

        $r1 = $this->getJson("/api/v1/npcs/{$npc->id}");
        $r2 = $this->getJson("/api/v1/npcs/{$npc->id}");

        $r1->assertStatus(200);
        $r2->assertStatus(200);

        $v1 = $r1->json('data.hitPointsSuggested');
        $v2 = $r2->json('data.hitPointsSuggested');

        $this->assertNotNull($v1, 'hitPointsSuggested must be populated when hit_points is null');
        $this->assertEquals($v1, $v2, 'hitPointsSuggested must be deterministic across requests');

        // 5d8+5 → floor(5*4.5+5) = floor(27.5) = 27
        $this->assertEquals(27, $v1);
    }

    // ── Nullable arrays coalesce to [] ────────────────────────────────────────

    public function test_nullable_array_columns_coalesce_to_empty_arrays(): void
    {
        $npc = Npc::factory()->create([
            'saving_throw_proficiencies' => null,
            'skill_proficiencies'        => null,
            'damage_vulnerabilities'     => null,
            'damage_resistances'         => null,
            'damage_immunities'          => null,
            'condition_immunities'       => null,
            'languages'                  => null,
        ]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals([], $data['savingThrowProficiencies']);
        $this->assertEquals([], $data['skillProficiencies']);
        $this->assertEquals([], $data['damageVulnerabilities']);
        $this->assertEquals([], $data['damageResistances']);
        $this->assertEquals([], $data['damageImmunities']);
        $this->assertEquals([], $data['conditionImmunities']);
        $this->assertEquals([], $data['languages']);
    }

    // ── Folder path in NPC ────────────────────────────────────────────────────

    public function test_npc_folder_shows_path_string(): void
    {
        $parent = Folder::factory()->create(['name' => 'Campaigns', 'parent_id' => null]);
        $child  = Folder::factory()->create(['name' => 'Waterdeep', 'parent_id' => $parent->id]);
        $npc    = Npc::factory()->create(['folder_id' => $child->id]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $folder = $response->json('data.folder');
        $this->assertNotNull($folder);
        $this->assertEquals($child->id, $folder['id']);
        $this->assertEquals('Waterdeep', $folder['name']);
        $this->assertEquals('Campaigns / Waterdeep', $folder['path']);
    }

    public function test_npc_with_no_folder_has_null_folder(): void
    {
        $npc = Npc::factory()->create(['folder_id' => null]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");
        $response->assertStatus(200);

        $this->assertNull($response->json('data.folder'));
    }

    // ── Sort and direction ────────────────────────────────────────────────────

    public function test_bad_sort_returns_400_invalid_sort(): void
    {
        $response = $this->getJson('/api/v1/npcs?sort=totally_bogus');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_SORT');
    }

    public function test_bad_direction_returns_400(): void
    {
        $response = $this->getJson('/api/v1/npcs?direction=sideways');

        $response->assertStatus(400);
    }

    /**
     * The single most important regression test for CR sorting:
     * fractional CRs must sort numerically, not lexically.
     * Lexical order: "1/2" < "1/8" < "10" < "2" (/ sorts before digits)
     * Correct order: "1/8" < "1/2" < "2" < "10"
     */
    public function test_challenge_rating_sort_is_numeric_not_lexical(): void
    {
        Npc::factory()->create(['name' => 'A', 'challenge_rating' => '10']);
        Npc::factory()->create(['name' => 'B', 'challenge_rating' => '1/8']);
        Npc::factory()->create(['name' => 'C', 'challenge_rating' => '2']);
        Npc::factory()->create(['name' => 'D', 'challenge_rating' => '1/2']);

        $response = $this->getJson('/api/v1/npcs?sort=challengeRating&direction=asc');
        $response->assertStatus(200);

        $crs = array_column($response->json('data'), 'name');
        $this->assertEquals(['B', 'D', 'C', 'A'], $crs, 'CRs must sort 1/8 < 1/2 < 2 < 10, not lexically');
    }

    public function test_null_challenge_rating_sorts_last_in_asc(): void
    {
        Npc::factory()->create(['name' => 'WithCr',  'challenge_rating' => '1']);
        Npc::factory()->create(['name' => 'NullCr',  'challenge_rating' => null]);

        $response = $this->getJson('/api/v1/npcs?sort=challengeRating&direction=asc');
        $response->assertStatus(200);

        $names = array_column($response->json('data'), 'name');
        $this->assertEquals('WithCr', $names[0]);
        $this->assertEquals('NullCr', $names[1]);
    }

    // ── pageSize clamping ─────────────────────────────────────────────────────

    public function test_page_size_is_clamped_to_50(): void
    {
        Npc::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/npcs?pageSize=999');

        $response->assertStatus(200);
        $this->assertEquals(50, $response->json('meta.pageSize'));
    }

    // ── Unknown params ignored ────────────────────────────────────────────────

    public function test_unknown_query_params_are_silently_ignored(): void
    {
        Npc::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/npcs?garbage=yes&whatever=blorp');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.totalItems'));
    }

    // ── search ────────────────────────────────────────────────────────────────

    public function test_search_filters_by_name(): void
    {
        Npc::factory()->create(['name' => 'Grizzik the Fence']);
        Npc::factory()->create(['name' => 'V the Netrunner']);

        $response = $this->getJson('/api/v1/npcs?search=Grizzik');
        $response->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.totalItems'));
        $this->assertEquals('Grizzik the Fence', $response->json('data.0.name'));
    }

    public function test_empty_search_is_treated_as_no_filter(): void
    {
        Npc::factory()->count(3)->create();

        // Empty string converted to null by ConvertEmptyStringsToNull middleware
        $response = $this->getJson('/api/v1/npcs?search=');
        $response->assertStatus(200);

        $this->assertEquals(3, $response->json('meta.totalItems'));
    }

    // ── challengeRating filter ────────────────────────────────────────────────

    public function test_challenge_rating_filter(): void
    {
        Npc::factory()->create(['challenge_rating' => '5']);
        Npc::factory()->create(['challenge_rating' => '3']);

        $response = $this->getJson('/api/v1/npcs?challengeRating=5');
        $response->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.totalItems'));
        $this->assertEquals('5', $response->json('data.0.challengeRating.value'));
    }

    // ── folderId + includeDescendants ─────────────────────────────────────────

    public function test_folder_id_filter(): void
    {
        $folder = Folder::factory()->create();
        Npc::factory()->count(2)->create(['folder_id' => $folder->id]);
        Npc::factory()->count(3)->create(['folder_id' => null]);

        $response = $this->getJson("/api/v1/npcs?folderId={$folder->id}");
        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.totalItems'));
    }

    public function test_include_descendants_includes_subfolder_npcs(): void
    {
        $parent = Folder::factory()->create();
        $child  = Folder::factory()->create(['parent_id' => $parent->id]);

        Npc::factory()->create(['folder_id' => $parent->id]);
        Npc::factory()->create(['folder_id' => $child->id]);
        Npc::factory()->create(['folder_id' => null]);

        $response = $this->getJson("/api/v1/npcs?folderId={$parent->id}&includeDescendants=true");
        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.totalItems'));
    }

    // ── ids bulk fetch ────────────────────────────────────────────────────────

    public function test_ids_parameter_returns_only_matching_ids(): void
    {
        $npc1 = Npc::factory()->create();
        $npc2 = Npc::factory()->create();
        $npc3 = Npc::factory()->create();

        $response = $this->getJson("/api/v1/npcs?ids={$npc1->id},{$npc3->id}");
        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$npc1->id, $npc3->id], $ids);
        $this->assertNotContains($npc2->id, $ids);
    }

    public function test_ids_parameter_silently_omits_nonexistent_ids(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->getJson("/api/v1/npcs?ids={$npc->id},99999,88888");
        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertEquals([$npc->id], $ids);
    }

    public function test_ids_parameter_excludes_template_ids(): void
    {
        $npc      = Npc::factory()->create();
        $template = Npc::factory()->template()->create();

        $response = $this->getJson("/api/v1/npcs?ids={$npc->id},{$template->id}");
        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertEquals([$npc->id], $ids);
    }

    public function test_ids_parameter_with_more_than_100_returns_400(): void
    {
        $idList = implode(',', range(1, 101));

        $response = $this->getJson("/api/v1/npcs?ids={$idList}");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_QUERY');
    }

    // ── N+1 prevention ───────────────────────────────────────────────────────

    /**
     * Assert that eager loading prevents N+1 queries on a full 25-item page.
     * The expected query count is: 1 count + 1 npcs + folder/traits/actions/
     * spellcasting/castingProfiles/innateEntries (6 eager-load batches) +
     * Folder::all() for path map = ~9–11 total. Assert < 15 as a safe ceiling.
     */
    public function test_list_does_not_n_plus_one_query(): void
    {
        $folder = Folder::factory()->create();

        Npc::factory()
            ->count(25)
            ->inFolder($folder)
            ->create()
            ->each(function (Npc $npc) {
                $npc->traits()->createMany([
                    ['name' => 'Pack Tactics', 'description' => 'Advantage when ally adjacent.'],
                ]);
                $npc->actions()->createMany([
                    ['name' => 'Bite', 'description' => 'Melee attack.', 'action_type' => 'action'],
                ]);
                NpcCastingProfile::factory()
                    ->for($npc)
                    ->innate()
                    ->create();
            });

        DB::enableQueryLog();

        $this->getJson('/api/v1/npcs?pageSize=25');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            15,
            $queryCount,
            "Expected < 15 queries for a 25-item page with full statblocks, got {$queryCount}. Likely N+1."
        );
    }
}
