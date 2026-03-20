<?php

namespace Tests\Feature;

use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_show_returns_404_for_non_template(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->get(route('templates.show', $npc));

        $response->assertStatus(404);
    }
}
