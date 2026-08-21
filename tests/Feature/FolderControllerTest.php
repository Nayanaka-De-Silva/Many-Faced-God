<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Npc;
use App\Models\NpcNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FolderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_folders(): void
    {
        $folder = Folder::factory()->create(['name' => 'Test Folder']);

        $response = $this->get(route('folders.index'));

        $response->assertStatus(200);
        $response->assertSee('Test Folder');
    }

    public function test_create_displays_form(): void
    {
        $response = $this->get(route('folders.create'));

        $response->assertStatus(200);
        $response->assertSee('Create Folder');
    }

    public function test_store_creates_folder(): void
    {
        $response = $this->post(route('folders.store'), [
            'name' => 'New Folder',
            'description' => 'A test folder',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('folders', ['name' => 'New Folder']);
    }

    public function test_store_creates_subfolder(): void
    {
        $parent = Folder::factory()->create(['name' => 'Parent']);

        $response = $this->post(route('folders.store'), [
            'name' => 'Child Folder',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('folders', [
            'name' => 'Child Folder',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('folders.store'), []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_show_displays_folder(): void
    {
        $folder = Folder::factory()->create(['name' => 'Display Folder']);

        $response = $this->get(route('folders.show', $folder));

        $response->assertStatus(200);
        $response->assertSee('Display Folder');
    }

    public function test_show_displays_npcs_in_folder(): void
    {
        $folder = Folder::factory()->create();
        $npc = Npc::factory()->inFolder($folder)->create(['name' => 'Folder NPC']);

        $response = $this->get(route('folders.show', $folder));

        $response->assertStatus(200);
        $response->assertSee('Folder NPC');
    }

    public function test_show_separates_actual_npcs_from_templates_in_folder(): void
    {
        $folder = Folder::factory()->create();
        $npc = Npc::factory()->inFolder($folder)->create(['name' => 'Folder NPC']);
        $template = Npc::factory()->template()->inFolder($folder)->create(['name' => 'Folder Template']);

        $response = $this->get(route('folders.show', $folder));

        $response->assertStatus(200);
        $response->assertSee('NPCs in this Folder');
        $response->assertSee('Templates in this Folder');
        $response->assertSee('Folder NPC');
        $response->assertSee('Folder Template');
        $response->assertSee(route('npcs.show', $npc), false);
        $response->assertSee(route('templates.show', $template), false);
        $response->assertDontSee(route('npcs.show', $template), false);
    }

    public function test_index_displays_separate_npc_and_template_counts_for_folders(): void
    {
        $folder = Folder::factory()->create(['name' => 'Mixed Folder']);
        Npc::factory()->inFolder($folder)->create();
        Npc::factory()->template()->inFolder($folder)->create();

        $response = $this->get(route('folders.index'));

        $response->assertStatus(200);
        $response->assertSee('Mixed Folder');
        $response->assertSee('1 NPC');
        $response->assertSee('1 Template');
    }

    public function test_show_displays_separate_npc_and_template_counts_for_subfolders(): void
    {
        $parent = Folder::factory()->create();
        $child = Folder::factory()->create([
            'name' => 'Child Folder',
            'parent_id' => $parent->id,
        ]);

        Npc::factory()->inFolder($child)->create();
        Npc::factory()->template()->inFolder($child)->create();

        $response = $this->get(route('folders.show', $parent));

        $response->assertStatus(200);
        $response->assertSee('Child Folder');
        $response->assertSee('1 NPC');
        $response->assertSee('1 Template');
    }

    public function test_show_displays_notes_preview_for_npcs_in_folder(): void
    {
        $folder = Folder::factory()->create();
        $npc = Npc::factory()->inFolder($folder)->create(['name' => 'Folder Preview NPC']);
        NpcNote::factory()->create([
            'npc_id'      => $npc->id,
            'title'       => 'Folder Note',
            'description' => 'First folder sentence. Second folder sentence. Third folder sentence.',
            'sort_order'  => 0,
        ]);

        $response = $this->get(route('folders.show', $folder));

        $response->assertStatus(200);
        $response->assertSee('First folder sentence. Second folder sentence.');
        $response->assertDontSee('Third folder sentence.');
    }

    public function test_edit_displays_form(): void
    {
        $folder = Folder::factory()->create(['name' => 'Edit Folder']);

        $response = $this->get(route('folders.edit', $folder));

        $response->assertStatus(200);
        $response->assertSee('Edit Folder');
    }

    public function test_update_modifies_folder(): void
    {
        $folder = Folder::factory()->create(['name' => 'Old Name']);

        $response = $this->put(route('folders.update', $folder), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'New Name']);
    }

    public function test_update_prevents_circular_parent(): void
    {
        $folder = Folder::factory()->create();

        $response = $this->put(route('folders.update', $folder), [
            'name' => $folder->name,
            'parent_id' => $folder->id,
        ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_destroy_deletes_folder(): void
    {
        $folder = Folder::factory()->create();

        $response = $this->delete(route('folders.destroy', $folder));

        $response->assertRedirect(route('folders.index'));
        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
    }

    public function test_destroy_moves_npcs_to_parent(): void
    {
        $parent = Folder::factory()->create();
        $folder = Folder::factory()->create(['parent_id' => $parent->id]);
        $npc = Npc::factory()->inFolder($folder)->create();

        $this->delete(route('folders.destroy', $folder));

        $npc->refresh();
        $this->assertEquals($parent->id, $npc->folder_id);
    }

    public function test_index_does_not_flatten_nested_folders_as_root_cards(): void
    {
        $root = Folder::factory()->create(['name' => 'Root Folder']);
        $child = Folder::factory()->create(['name' => 'Child Folder', 'parent_id' => $root->id]);

        $response = $this->get(route('folders.index'));

        $response->assertStatus(200);
        $this->assertEquals(1, substr_count($response->getContent(), 'data-folder-root-node'));
        $response->assertSee('Child Folder');
    }

    public function test_index_eager_loads_full_tree_without_n_plus_one(): void
    {
        // Depth-2 chain
        $root1 = Folder::factory()->create(['name' => 'Root 1']);
        Folder::factory()->create(['name' => 'Child 1', 'parent_id' => $root1->id]);

        DB::enableQueryLog();
        $this->get(route('folders.index'));
        $countWith2 = count(DB::getQueryLog());

        // Add depth-4 chain; flush right before the second hit to exclude factory INSERTs
        $root2 = Folder::factory()->create(['name' => 'Root 2']);
        $child2a = Folder::factory()->create(['name' => 'Child 2a', 'parent_id' => $root2->id]);
        $child2b = Folder::factory()->create(['name' => 'Child 2b', 'parent_id' => $child2a->id]);
        Folder::factory()->create(['name' => 'Child 2c', 'parent_id' => $child2b->id]);

        DB::flushQueryLog();
        $this->get(route('folders.index'));
        $countWith4 = count(DB::getQueryLog());

        $this->assertEquals($countWith2, $countWith4);
    }

    public function test_edit_parent_picker_excludes_descendants(): void
    {
        $folder = Folder::factory()->create(['name' => 'Edit Me']);
        $child = Folder::factory()->create(['name' => 'My Child', 'parent_id' => $folder->id]);
        Folder::factory()->create(['name' => 'My Grandchild', 'parent_id' => $child->id]);
        Folder::factory()->create(['name' => 'Other Folder']);

        $response = $this->get(route('folders.edit', $folder));

        $response->assertStatus(200);
        $response->assertDontSee('My Child');
        $response->assertDontSee('My Grandchild');
        $response->assertSee('Other Folder');
    }
}
