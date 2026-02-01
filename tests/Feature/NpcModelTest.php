<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Npc;
use App\Models\NpcAction;
use App\Models\NpcTrait;
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

    public function test_npc_by_challenge_rating_scope(): void
    {
        Npc::factory()->create(['challenge_rating' => '1/4']);
        Npc::factory()->create(['challenge_rating' => '1']);
        Npc::factory()->create(['challenge_rating' => '1']);

        $this->assertCount(1, Npc::byChallengeRating('1/4')->get());
        $this->assertCount(2, Npc::byChallengeRating('1')->get());
    }
}
