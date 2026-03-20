<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_show_displays_notes_preview_for_npcs_in_folder(): void
    {
        $folder = Folder::factory()->create();
        Npc::factory()->inFolder($folder)->create([
            'name' => 'Folder Preview NPC',
            'notes' => 'First folder sentence. Second folder sentence. Third folder sentence.',
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
}
