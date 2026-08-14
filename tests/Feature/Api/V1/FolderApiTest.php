<?php

namespace Tests\Feature\Api\V1;

use App\Models\Folder;
use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderApiTest extends TestCase
{
    use RefreshDatabase;

    // ── /folders index ───────────────────────────────────────────────────────

    public function test_folders_index_returns_200(): void
    {
        Folder::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/folders');
        $response->assertStatus(200);

        $this->assertIsArray($response->json('data'));
    }

    public function test_folders_index_tree_nests_children(): void
    {
        $parent = Folder::factory()->create(['name' => 'Campaigns', 'parent_id' => null]);
        $child  = Folder::factory()->create(['name' => 'Waterdeep', 'parent_id' => $parent->id]);
        $grandchild = Folder::factory()->create(['name' => 'Harbor District', 'parent_id' => $child->id]);

        $response = $this->getJson('/api/v1/folders');
        $response->assertStatus(200);

        $tree = $response->json('data');

        // Root should contain Campaigns
        $campaigns = collect($tree)->firstWhere('name', 'Campaigns');
        $this->assertNotNull($campaigns, 'Root folder Campaigns must be in the tree');

        $waterdeep = collect($campaigns['children'])->firstWhere('name', 'Waterdeep');
        $this->assertNotNull($waterdeep, 'Waterdeep must be a child of Campaigns');

        $harbor = collect($waterdeep['children'])->firstWhere('name', 'Harbor District');
        $this->assertNotNull($harbor, 'Harbor District must be a grandchild of Campaigns');
    }

    public function test_folders_index_includes_npc_and_template_counts(): void
    {
        $folder = Folder::factory()->create();

        Npc::factory()->count(3)->create(['folder_id' => $folder->id]);
        Npc::factory()->template()->count(2)->create(['folder_id' => $folder->id]);

        $response = $this->getJson('/api/v1/folders');
        $response->assertStatus(200);

        $tree = $response->json('data');
        $found = collect($tree)->firstWhere('id', $folder->id);

        $this->assertNotNull($found);
        $this->assertEquals(3, $found['npcCount']);
        $this->assertEquals(2, $found['templateCount']);
    }

    /**
     * Depth cap must fire: a folder cycle (parent_id loop) must not cause
     * infinite recursion or a 500. The response must still return.
     */
    public function test_folder_depth_cap_prevents_infinite_recursion_on_cycle(): void
    {
        // Create a folder cycle: A → B → A (A's parent is B, B's parent is A).
        // Bypass model logic by writing directly to the DB.
        $folderA = Folder::factory()->create(['name' => 'FolderA', 'parent_id' => null]);
        $folderB = Folder::factory()->create(['name' => 'FolderB', 'parent_id' => $folderA->id]);

        // Force the cycle by updating A's parent to B at DB level
        \DB::table('folders')->where('id', $folderA->id)->update(['parent_id' => $folderB->id]);

        // Must not hang or 500 — the depth cap must prevent infinite recursion
        $response = $this->getJson('/api/v1/folders');

        $this->assertNotEquals(500, $response->getStatusCode(), 'Cyclic folder must not cause a 500');
        $this->assertLessThanOrEqual(200, $response->getStatusCode());
    }

    /**
     * The existing cycle test above only exercises the tree-building path
     * (FolderController::MAX_DEPTH). GET /folders/{id} instead walks
     * $folder->breadcrumb (Folder::getBreadcrumbAttribute()), a separate,
     * pre-existing accessor that had no depth/cycle guard of its own until
     * this fix — a corrupted parent_id cycle would otherwise hang the
     * request via its bare `while ($folder)` loop, on this newly-public,
     * unauthenticated endpoint.
     */
    public function test_folder_show_breadcrumb_does_not_hang_on_cycle(): void
    {
        $folderA = Folder::factory()->create(['name' => 'FolderA', 'parent_id' => null]);
        $folderB = Folder::factory()->create(['name' => 'FolderB', 'parent_id' => $folderA->id]);
        \DB::table('folders')->where('id', $folderA->id)->update(['parent_id' => $folderB->id]);

        $response = $this->getJson("/api/v1/folders/{$folderA->id}");

        $this->assertNotEquals(500, $response->getStatusCode(), 'Cyclic breadcrumb must not cause a 500');
        $response->assertStatus(200);
        $this->assertIsArray($response->json('data.breadcrumb'));
    }

    /**
     * The {folder} route param is constrained to digits — see the matching
     * regression test in NpcApiTest for why this matters.
     */
    public function test_folders_show_returns_404_not_500_for_non_numeric_id(): void
    {
        $response = $this->getJson('/api/v1/folders/abc');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ── /folders/{id} show ───────────────────────────────────────────────────

    public function test_folders_show_returns_folder_with_breadcrumb(): void
    {
        $parent = Folder::factory()->create(['name' => 'Campaigns', 'parent_id' => null]);
        $child  = Folder::factory()->create(['name' => 'Waterdeep', 'parent_id' => $parent->id]);

        $response = $this->getJson("/api/v1/folders/{$child->id}");
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($child->id, $data['id']);
        $this->assertEquals('Waterdeep', $data['name']);

        // Breadcrumb must be present
        $this->assertArrayHasKey('breadcrumb', $data);
        $this->assertNotEmpty($data['breadcrumb']);

        // Path must reflect the full folder hierarchy
        $this->assertArrayHasKey('path', $data);
        $this->assertStringContainsString('Campaigns', $data['path']);
        $this->assertStringContainsString('Waterdeep', $data['path']);
    }

    public function test_folders_show_includes_counts(): void
    {
        $folder = Folder::factory()->create();
        Npc::factory()->count(2)->create(['folder_id' => $folder->id]);
        Npc::factory()->template()->count(1)->create(['folder_id' => $folder->id]);

        $response = $this->getJson("/api/v1/folders/{$folder->id}");
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(2, $data['npcCount']);
        $this->assertEquals(1, $data['templateCount']);
    }

    public function test_folders_show_includes_immediate_children(): void
    {
        $parent = Folder::factory()->create(['name' => 'Parent']);
        $child1 = Folder::factory()->create(['name' => 'Child1', 'parent_id' => $parent->id]);
        $child2 = Folder::factory()->create(['name' => 'Child2', 'parent_id' => $parent->id]);
        // Grandchild — should NOT appear in parent's show() direct children
        Folder::factory()->create(['name' => 'Grandchild', 'parent_id' => $child1->id]);

        $response = $this->getJson("/api/v1/folders/{$parent->id}");
        $response->assertStatus(200);

        $children = $response->json('data.children');
        $this->assertNotNull($children);

        $childNames = array_column($children, 'name');
        $this->assertContains('Child1', $childNames);
        $this->assertContains('Child2', $childNames);
    }

    public function test_folders_show_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/v1/folders/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ── Root folder has null parent ───────────────────────────────────────────

    public function test_folders_index_root_folders_appear_at_top_level(): void
    {
        $root1 = Folder::factory()->create(['name' => 'Root1', 'parent_id' => null]);
        $root2 = Folder::factory()->create(['name' => 'Root2', 'parent_id' => null]);
        Folder::factory()->create(['name' => 'Sub', 'parent_id' => $root1->id]);

        $response = $this->getJson('/api/v1/folders');
        $response->assertStatus(200);

        $tree = $response->json('data');
        $names = array_column($tree, 'name');

        $this->assertContains('Root1', $names);
        $this->assertContains('Root2', $names);
        $this->assertNotContains('Sub', $names, 'Sub must not appear at root level');
    }
}
