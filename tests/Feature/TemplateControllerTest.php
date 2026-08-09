<?php

namespace Tests\Feature;

use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use DOMDocument;
use DOMXPath;

class TemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_templates(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Test Template']);
        $npc = Npc::factory()->create(['name' => 'Regular NPC']);

        $response = $this->get(route('templates.index'));

        $response->assertStatus(200);
        $response->assertSee('Test Template');
        $response->assertDontSee('Regular NPC');
    }

    public function test_index_create_template_link_preserves_template_flag(): void
    {
        $response = $this->get(route('templates.index'));

        $response->assertStatus(200);
        $response->assertSee(route('npcs.create', ['is_template' => 1]), escape: false);
    }

    public function test_show_displays_template(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Display Template']);

        $response = $this->get(route('templates.show', $template));

        $response->assertStatus(200);
        $response->assertSee('Display Template');
        $response->assertSee('Template');
    }

    public function test_show_displays_template_notes_without_character_notes(): void
    {
        $template = Npc::factory()->template()->create([
            'name' => 'Noble Template',
            'notes' => 'Use this for courtly intrigue scenes.',
            'personality_traits' => 'Should stay hidden.',
            'ideals' => 'Should stay hidden.',
            'bonds' => 'Should stay hidden.',
            'flaws' => 'Should stay hidden.',
        ]);

        $response = $this->get(route('templates.show', $template));

        $response->assertStatus(200);
        $response->assertSee('Noble Template');
        $response->assertSee('Notes');
        $response->assertSee('Use this for courtly intrigue scenes.');
        $response->assertDontSee('Personality Traits');
        $response->assertDontSee('Should stay hidden.');
    }

    public function test_show_displays_attack_actions(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Knight Template']);
        $template->actions()->create([
            'name' => 'Flaming Longsword',
            'description' => 'The target ignites briefly after the strike.',
            'action_type' => \App\Models\NpcAction::TYPE_ATTACK,
            'attack_kind' => 'melee',
            'attack_range_text' => '5 ft.',
            'attack_to_hit' => 5,
            'attack_target' => 'One target',
            'attack_hit' => '5 (1d10) slashing damage',
            'attack_hit_2' => '2d6+5 fire damage',
        ]);

        $response = $this->get(route('templates.show', $template));

        $response->assertStatus(200);
        $response->assertSee('Flaming Longsword');
        $response->assertSee('Melee Weapon Attack: +5, Reach 5 ft., One target');
        $response->assertSee('Hit: 5 (1d10) slashing damage (plus 2d6+5 fire damage)');
        $response->assertSee('The target ignites briefly after the strike.');
    }

    public function test_index_marks_template_links_that_need_hit_point_choice(): void
    {
        $needsChoice = Npc::factory()->template()->create([
            'name' => 'Soldier Template',
            'hit_points' => 16,
            'hit_dice' => '3d8+3',
        ]);
        $noChoice = Npc::factory()->template()->create([
            'name' => 'Static Template',
            'hit_points' => 12,
            'hit_dice' => null,
        ]);

        $response = $this->get(route('templates.index'));

        $this->assertTemplateChoiceRequirement($response, $needsChoice, true);
        $this->assertTemplateChoiceRequirement($response, $noChoice, false);
    }

    public function test_show_marks_create_npc_link_that_needs_hit_point_choice(): void
    {
        $template = Npc::factory()->template()->create([
            'name' => 'Commander Template',
            'hit_points' => 45,
            'hit_dice' => '6d8+12',
        ]);

        $response = $this->get(route('templates.show', $template));

        $this->assertTemplateChoiceRequirement($response, $template, true);
        $response->assertSee('Choose Hit Points');
    }

    public function test_show_renders_template_card_in_gray(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Gray Template']);

        $response = $this->get(route('templates.show', $template));

        $response->assertStatus(200);
        $response->assertSee('class="card npc-card template-card"', false);
    }

    public function test_show_template_badge_is_legible_on_gray_header(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Badge Template']);

        $response = $this->get(route('templates.show', $template));

        $response->assertStatus(200);
        $response->assertSee('badge bg-dark">Template', false);
    }

    public function test_show_returns_404_for_non_template(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->get(route('templates.show', $npc));

        $response->assertStatus(404);
    }

    private function assertTemplateChoiceRequirement(TestResponse $response, Npc $template, bool $requiresChoice): void
    {
        $dom = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);

        $dom->loadHTML($response->getContent());

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $link = (new DOMXPath($dom))
            ->query(sprintf('//a[@href="%s"]', route('npcs.create', ['from_template' => $template->id])))
            ->item(0);

        $this->assertNotNull($link, 'Expected the template creation link to be present.');
        $this->assertSame(
            $requiresChoice ? 'true' : 'false',
            $link->attributes->getNamedItem('data-template-hit-point-choice-required')?->nodeValue
        );
    }
}
