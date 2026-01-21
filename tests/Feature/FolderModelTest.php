<?php

namespace Tests\Feature;

use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_has_parent(): void
    {
        $parent = Folder::factory()->create(['name' => 'Parent']);
        $child = Folder::factory()->create(['parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_folder_has_children(): void
    {
        $parent = Folder::factory()->create();
        Folder::factory()->count(3)->create(['parent_id' => $parent->id]);

        $this->assertCount(3, $parent->children);
    }

    public function test_folder_breadcrumb(): void
    {
        $root = Folder::factory()->create(['name' => 'Root']);
        $level1 = Folder::factory()->create(['name' => 'Level 1', 'parent_id' => $root->id]);
        $level2 = Folder::factory()->create(['name' => 'Level 2', 'parent_id' => $level1->id]);

        $breadcrumb = $level2->breadcrumb;

        $this->assertCount(3, $breadcrumb);
        $this->assertEquals('Root', $breadcrumb[0]->name);
        $this->assertEquals('Level 1', $breadcrumb[1]->name);
        $this->assertEquals('Level 2', $breadcrumb[2]->name);
    }
}
