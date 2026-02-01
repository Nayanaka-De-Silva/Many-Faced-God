<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard');
    }

    public function test_dashboard_shows_counts(): void
    {
        Npc::factory()->count(3)->create();
        Npc::factory()->template()->count(2)->create();
        Folder::factory()->count(4)->create();

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        // Should show 3 NPCs (not templates)
        $response->assertSee('3');
        // Should show 2 templates
        $response->assertSee('2');
        // Should show 4 folders
        $response->assertSee('4');
    }

    public function test_dashboard_shows_recent_npcs(): void
    {
        $npc = Npc::factory()->create(['name' => 'Recent NPC']);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Recent NPC');
    }
}
