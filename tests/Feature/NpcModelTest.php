<?php

namespace Tests\Feature;

use App\Models\Npc;
use App\Models\Folder;
use App\Models\NpcTrait;
use App\Models\NpcAction;
use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpcModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_npc_belongs_to_folder(): void
    {
        $folder = Folder::factory()->create();
        $npc = Npc::factory()->inFolder($folder)->create();

        $this->assertEquals($folder->id, $npc->folder->id);
    }

    public function test_npc_has_many_traits(): void
    {
        $npc = Npc::factory()->create();
        NpcTrait::factory()->count(3)->create(['npc_id' => $npc->id]);

        $this->assertCount(3, $npc->traits);
    }

    public function test_npc_has_many_actions(): void
    {
        $npc = Npc::factory()->create();
        NpcAction::factory()->count(2)->create(['npc_id' => $npc->id]);

        $this->assertCount(2, $npc->actions);
    }

    public function test_npc_modifier_attributes(): void
    {
        $npc = Npc::factory()->create([
            'strength' => 14,
            'dexterity' => 8,
            'constitution' => 10,
            'intelligence' => 18,
            'wisdom' => 12,
            'charisma' => 6,
        ]);

        $this->assertEquals(2, $npc->strength_modifier);
        $this->assertEquals(-1, $npc->dexterity_modifier);
        $this->assertEquals(0, $npc->constitution_modifier);
        $this->assertEquals(4, $npc->intelligence_modifier);
        $this->assertEquals(1, $npc->wisdom_modifier);
        $this->assertEquals(-2, $npc->charisma_modifier);
    }

    public function test_npc_passive_perception(): void
    {
        $npc = Npc::factory()->create([
            'wisdom' => 14, // +2 modifier
            'proficiency_bonus' => 2,
            'skill_proficiencies' => ['Perception'],
        ]);

        // 10 + 2 (wis mod) + 2 (proficiency) = 14
        $this->assertEquals(14, $npc->passive_perception);
    }

    public function test_npc_passive_perception_without_proficiency(): void
    {
        $npc = Npc::factory()->create([
            'wisdom' => 14, // +2 modifier
            'skill_proficiencies' => [],
        ]);

        // 10 + 2 (wis mod) = 12
        $this->assertEquals(12, $npc->passive_perception);
    }

    public function test_npc_duplicate_creates_copy(): void
    {
        $npc = Npc::factory()->create(['name' => 'Original']);
        NpcTrait::factory()->create(['npc_id' => $npc->id, 'name' => 'Test Trait']);
        NpcAction::factory()->create(['npc_id' => $npc->id, 'name' => 'Test Action']);

        $clone = $npc->duplicate();

        $this->assertEquals('Original (Copy)', $clone->name);
        $this->assertNotEquals($npc->id, $clone->id);
        $this->assertFalse($clone->is_template);
        $this->assertCount(1, $clone->traits);
        $this->assertCount(1, $clone->actions);
    }

    public function test_npc_duplicate_copies_attack_action_fields(): void
    {
        $npc = Npc::factory()->create(['name' => 'Original']);
        NpcAction::factory()->attackAction()->create(['npc_id' => $npc->id]);

        $clone = $npc->duplicate();
        $cloneAction = $clone->actions->first();

        $this->assertEquals(NpcAction::TYPE_ATTACK, $cloneAction->action_type);
        $this->assertEquals('melee', $cloneAction->attack_kind);
        $this->assertEquals('5 ft.', $cloneAction->attack_range_text);
        $this->assertEquals(5, $cloneAction->attack_to_hit);
        $this->assertEquals('One target', $cloneAction->attack_target);
        $this->assertEquals('5 (1d10) slashing damage', $cloneAction->attack_hit);
        $this->assertEquals('2d6+5 fire damage', $cloneAction->attack_hit_2);
    }

    public function test_attack_action_formats_display_lines(): void
    {
        $action = NpcAction::factory()->attackAction()->make();

        $this->assertEquals(
            'Melee Weapon Attack: +5, Reach 5 ft., One target',
            $action->formatted_attack_line
        );
        $this->assertEquals(
            'Hit: 5 (1d10) slashing damage (plus 2d6+5 fire damage)',
            $action->formatted_hit_line
        );
    }

    public function test_template_requires_hit_point_choice_when_both_values_are_present(): void
    {
        $template = Npc::factory()->template()->make([
            'hit_points' => 18,
            'hit_dice' => '4d8',
        ]);

        $this->assertTrue($template->requiresTemplateHitPointChoice());
    }

    public function test_template_does_not_require_hit_point_choice_without_hit_dice(): void
    {
        $template = Npc::factory()->template()->make([
            'hit_points' => 18,
            'hit_dice' => null,
        ]);

        $this->assertFalse($template->requiresTemplateHitPointChoice());
    }

    public function test_npc_duplicate_copies_note_fields(): void
    {
        $npc = Npc::factory()->create([
            'notes' => 'Tracks party debt.',
            'personality_traits' => 'Always taps the table before speaking.',
            'ideals' => 'Order must be preserved.',
            'bonds' => 'Her guild apprentice.',
            'flaws' => 'Overconfident when pressured.',
        ]);

        $clone = $npc->duplicate();

        $this->assertEquals('Tracks party debt.', $clone->notes);
        $this->assertEquals('Always taps the table before speaking.', $clone->personality_traits);
        $this->assertEquals('Order must be preserved.', $clone->ideals);
        $this->assertEquals('Her guild apprentice.', $clone->bonds);
        $this->assertEquals('Overconfident when pressured.', $clone->flaws);
    }

    public function test_npc_templates_scope(): void
    {
        Npc::factory()->count(2)->create(['is_template' => false]);
        Npc::factory()->count(3)->create(['is_template' => true]);

        $this->assertCount(3, Npc::templates()->get());
    }

    public function test_npc_npcs_scope(): void
    {
        Npc::factory()->count(2)->create(['is_template' => false]);
        Npc::factory()->count(3)->create(['is_template' => true]);

        $this->assertCount(2, Npc::npcs()->get());
    }

    public function test_npc_search_scope(): void
    {
        Npc::factory()->create(['name' => 'Goblin King']);
        Npc::factory()->create(['name' => 'Orc Warrior']);
        Npc::factory()->create(['name' => 'Goblin Archer']);

        $this->assertCount(2, Npc::search('Goblin')->get());
        $this->assertCount(1, Npc::search('Warrior')->get());
    }

    public function test_npc_duplicate_copies_casting_profiles_and_innate_entries(): void
    {
        $npc = Npc::factory()->create(['name' => 'Original']);

        $innateProfile = NpcCastingProfile::factory()->innate()->create([
            'npc_id' => $npc->id,
            'race_or_origin' => 'Drow Magic',
        ]);
        NpcInnateSpellEntry::factory()->atWill()->create([
            'casting_profile_id' => $innateProfile->id,
            'spell_name' => 'Dancing Lights',
        ]);
        NpcInnateSpellEntry::factory()->perDay(1)->create([
            'casting_profile_id' => $innateProfile->id,
            'spell_name' => 'Darkness',
        ]);

        NpcCastingProfile::factory()->spellcasting()->create(['npc_id' => $npc->id]);

        $clone = $npc->duplicate();

        // Clone has its own separate profiles
        $cloneProfiles = NpcCastingProfile::where('npc_id', $clone->id)->get();
        $this->assertCount(2, $cloneProfiles);

        $originalProfileIds = NpcCastingProfile::where('npc_id', $npc->id)->pluck('id');
        foreach ($cloneProfiles as $cloneProfile) {
            $this->assertNotContains($cloneProfile->id, $originalProfileIds->toArray());
        }

        // Innate profile fields were copied correctly
        $cloneInnate = $cloneProfiles->firstWhere('casting_type', 'Innate');
        $this->assertNotNull($cloneInnate);
        $this->assertSame('Drow Magic', $cloneInnate->race_or_origin);

        // Innate entries were cloned with correct field values
        $cloneEntries = NpcInnateSpellEntry::where('casting_profile_id', $cloneInnate->id)->get();
        $this->assertCount(2, $cloneEntries);
        $this->assertNotNull($cloneEntries->firstWhere('spell_name', 'Dancing Lights'));
        $this->assertNotNull($cloneEntries->firstWhere('spell_name', 'Darkness'));

        // Entries belong to clone's profile, not original's
        $originalEntryIds = NpcInnateSpellEntry::where('casting_profile_id', $innateProfile->id)->pluck('id');
        foreach ($cloneEntries as $cloneEntry) {
            $this->assertNotContains($cloneEntry->id, $originalEntryIds->toArray());
        }
    }

    public function test_npc_duplicate_eager_loads_casting_profiles_to_avoid_n_plus_one(): void
    {
        $npc = Npc::factory()->create(['name' => 'Many Profiles']);

        foreach (range(1, 4) as $i) {
            $profile = NpcCastingProfile::factory()->innate()->create(['npc_id' => $npc->id]);
            NpcInnateSpellEntry::factory()->atWill()->create(['casting_profile_id' => $profile->id]);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $npc->fresh()->duplicate();
        $selects = collect(\Illuminate\Support\Facades\DB::getQueryLog())
            ->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'select')
                && (str_contains($q['query'], 'npc_casting_profiles') || str_contains($q['query'], 'npc_innate_spell_entries')));
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Eager-loading both levels means exactly one SELECT for profiles and one for
        // entries (a whereIn across all profile ids), regardless of profile count — not
        // one extra SELECT per profile for its innate entries.
        $this->assertCount(2, $selects, 'duplicate() should issue exactly 2 SELECTs (profiles, entries) regardless of profile count.');
    }

    public function test_npc_by_challenge_rating_scope(): void
    {
        Npc::factory()->create(['challenge_rating' => '1/4']);
        Npc::factory()->create(['challenge_rating' => '1']);
        Npc::factory()->create(['challenge_rating' => '1']);

        $this->assertCount(1, Npc::byChallengeRating('1/4')->get());
        $this->assertCount(2, Npc::byChallengeRating('1')->get());
    }

    public function test_formatted_senses_formats_ft_category_as_range_ft(): void
    {
        $npc = Npc::factory()->create([
            'senses' => [['type' => 'Darkvision', 'range' => 60, 'category' => 'ft']],
        ]);

        $this->assertEquals(['Darkvision 60 ft.'], $npc->formatted_senses);
    }

    public function test_formatted_senses_formats_dc_category_without_suffix(): void
    {
        $npc = Npc::factory()->create([
            'senses' => [['type' => 'Passive Perception', 'range' => 14, 'category' => 'dc']],
        ]);

        $this->assertEquals(['Passive Perception 14'], $npc->formatted_senses);
    }

    public function test_formatted_senses_formats_other_category_as_passthrough(): void
    {
        $npc = Npc::factory()->create([
            'senses' => [['type' => 'Tremorsense', 'range' => '30 ft. (blind beyond this radius)', 'category' => 'other']],
        ]);

        $this->assertEquals(['Tremorsense 30 ft. (blind beyond this radius)'], $npc->formatted_senses);
    }

    public function test_formatted_senses_treats_missing_category_as_ft(): void
    {
        $npc = Npc::factory()->create([
            'senses' => [['type' => 'Darkvision', 'range' => 60]], // no category key — legacy row
        ]);

        $this->assertEquals(['Darkvision 60 ft.'], $npc->formatted_senses);
    }

    public function test_formatted_senses_returns_multiple_senses_in_order(): void
    {
        $npc = Npc::factory()->create([
            'senses' => [
                ['type' => 'Darkvision', 'range' => 60, 'category' => 'ft'],
                ['type' => 'Passive Perception', 'range' => 14, 'category' => 'dc'],
                ['type' => 'Tremorsense', 'range' => '10 ft.', 'category' => 'other'],
            ],
        ]);

        $this->assertEquals([
            'Darkvision 60 ft.',
            'Passive Perception 14',
            'Tremorsense 10 ft.',
        ], $npc->formatted_senses);
    }

    public function test_formatted_senses_trims_blank_range_without_double_space(): void
    {
        $npc = Npc::factory()->create([
            'senses' => [
                ['type' => 'Truesight', 'range' => '', 'category' => 'ft'],
                ['type' => 'Keen Smell', 'range' => '', 'category' => 'dc'],
            ],
        ]);

        $this->assertEquals([
            'Truesight ft.',
            'Keen Smell',
        ], $npc->formatted_senses);
    }
}
