<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_renders_collapse_toggle_button(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="sidebarToggle"', false);
        $response->assertSee('aria-controls="sidebarNav"', false);
    }

    public function test_sidebar_nav_labels_are_wrapped_for_collapsed_mode(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('<span class="nav-label">Dashboard</span>', false);
        $response->assertSee('<span class="nav-label">NPCs</span>', false);
        $response->assertSee('<span class="nav-label">Templates</span>', false);
        $response->assertSee('<span class="nav-label">Folders</span>', false);
        $response->assertSee('<span class="nav-label">Settings</span>', false);
    }

    public function test_sidebar_nav_links_expose_accessible_labels(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        // Attribute order (title then aria-label) mirrors the markup exactly.
        $response->assertSee('title="Dashboard" aria-label="Dashboard"', false);
        $response->assertSee('title="NPCs" aria-label="NPCs"', false);
        $response->assertSee('title="Templates" aria-label="Templates"', false);
        $response->assertSee('title="Folders" aria-label="Folders"', false);
        $response->assertSee('title="Settings" aria-label="Settings"', false);
    }

    public function test_mobile_nav_contains_settings_link(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Settings', false);
        $response->assertSee(route('settings.edit'), false);
    }

    public function test_sidebar_state_script_uses_expected_storage_key(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee("localStorage.getItem('mfg.sidebar')", false);
    }

    public function test_sidebar_marks_the_current_route_active(): void
    {
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('class="nav-link active" href="' . route('dashboard') . '"', false);

        $npcsResponse = $this->get(route('npcs.index'));
        $npcsResponse->assertStatus(200);
        $npcsResponse->assertSee('class="nav-link active" href="' . route('npcs.index') . '"', false);
        $npcsResponse->assertDontSee('class="nav-link active" href="' . route('dashboard') . '"', false);
    }

    public function test_mobile_nav_is_left_intact(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="mobileNav"', false);
        $response->assertSee('navbar-toggler', false);
    }
}
