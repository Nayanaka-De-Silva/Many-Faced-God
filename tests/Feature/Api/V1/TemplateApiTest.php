<?php

namespace Tests\Feature\Api\V1;

use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateApiTest extends TestCase
{
    use RefreshDatabase;

    // ── /templates list ──────────────────────────────────────────────────────

    public function test_templates_index_returns_200(): void
    {
        Npc::factory()->template()->count(3)->create();

        $response = $this->getJson('/api/v1/templates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['page', 'pageSize', 'totalItems', 'totalPages'],
            ]);
    }

    public function test_every_entry_in_templates_is_a_template(): void
    {
        Npc::factory()->template()->count(3)->create();
        Npc::factory()->count(2)->create(); // non-templates, must be excluded

        $response = $this->getJson('/api/v1/templates');
        $response->assertStatus(200);

        $this->assertEquals(3, $response->json('meta.totalItems'));

        foreach ($response->json('data') as $entry) {
            $this->assertTrue($entry['isTemplate'], 'Every /templates entry must have isTemplate: true');
        }
    }

    public function test_templates_index_excludes_non_templates(): void
    {
        Npc::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/templates');
        $response->assertStatus(200);

        $this->assertEquals(0, $response->json('meta.totalItems'));
    }

    // ── /templates/{id} show ─────────────────────────────────────────────────

    public function test_templates_show_returns_template(): void
    {
        $template = Npc::factory()->template()->create(['name' => 'Goblin Shaman']);

        $response = $this->getJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.name', 'Goblin Shaman')
            ->assertJsonPath('data.isTemplate', true);
    }

    /**
     * A non-template id passed to /templates/{id} must 404.
     * NPCs are out of scope for the templates endpoint (is_template = true only).
     */
    public function test_templates_show_returns_404_for_non_template_id(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->getJson("/api/v1/templates/{$npc->id}");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_templates_show_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/v1/templates/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ── Template reuses NpcResource shape ────────────────────────────────────

    public function test_template_response_has_same_keys_as_npc_response(): void
    {
        $template = Npc::factory()->template()->create();
        $npc      = Npc::factory()->create();

        $templateResponse = $this->getJson("/api/v1/templates/{$template->id}");
        $npcResponse      = $this->getJson("/api/v1/npcs/{$npc->id}");

        $templateKeys = array_keys($templateResponse->json('data'));
        $npcKeys      = array_keys($npcResponse->json('data'));

        sort($templateKeys);
        sort($npcKeys);

        $this->assertEquals($npcKeys, $templateKeys, '/templates response must use the same NpcResource shape as /npcs');
    }

    // ── Sort params work on templates too ─────────────────────────────────────

    public function test_templates_bad_sort_returns_400(): void
    {
        $response = $this->getJson('/api/v1/templates?sort=bogus_field');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_SORT');
    }

    public function test_templates_page_size_clamped_to_50(): void
    {
        Npc::factory()->template()->count(2)->create();

        $response = $this->getJson('/api/v1/templates?pageSize=999');
        $response->assertStatus(200);

        $this->assertEquals(50, $response->json('meta.pageSize'));
    }

    /**
     * The {template} route param is constrained to digits — see the
     * matching regression test in NpcApiTest for why this matters (a
     * non-numeric segment would otherwise throw an uncaught TypeError
     * instead of yielding the documented 404).
     */
    public function test_templates_show_returns_404_not_500_for_non_numeric_id(): void
    {
        $response = $this->getJson('/api/v1/templates/abc');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
