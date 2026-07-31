<?php

namespace Tests\Feature;

use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_options_orders_depth_first_with_full_paths(): void
    {
        $a = Folder::factory()->create(['name' => 'A']);
        $b = Folder::factory()->create(['name' => 'B']);
        $a1 = Folder::factory()->create(['name' => 'A1', 'parent_id' => $a->id]);
        $a1a = Folder::factory()->create(['name' => 'A1a', 'parent_id' => $a1->id]);

        $options = Folder::treeOptions();

        $this->assertEquals([$a->id, $a1->id, $a1a->id, $b->id], $options->keys()->all());
        $this->assertEquals('A', $options[$a->id]);
        $this->assertEquals('A / A1', $options[$a1->id]);
        $this->assertEquals('A / A1 / A1a', $options[$a1a->id]);
        $this->assertEquals('B', $options[$b->id]);
    }

    public function test_tree_options_excludes_folder_and_descendants(): void
    {
        $a = Folder::factory()->create(['name' => 'A']);
        $b = Folder::factory()->create(['name' => 'B']);
        $a1 = Folder::factory()->create(['name' => 'A1', 'parent_id' => $a->id]);
        $a1a = Folder::factory()->create(['name' => 'A1a', 'parent_id' => $a1->id]);

        $options = Folder::treeOptions(excludeFolderId: $a->id);

        $this->assertFalse($options->has($a->id));
        $this->assertFalse($options->has($a1->id));
        $this->assertFalse($options->has($a1a->id));
        $this->assertTrue($options->has($b->id));
    }

    public function test_descendant_ids_returns_all_levels(): void
    {
        $root = Folder::factory()->create(['name' => 'Root']);
        $level1 = Folder::factory()->create(['name' => 'Level 1', 'parent_id' => $root->id]);
        $level2 = Folder::factory()->create(['name' => 'Level 2', 'parent_id' => $level1->id]);

        $ids = Folder::descendantIds($root->id);

        $this->assertEqualsCanonicalizing([$level1->id, $level2->id], $ids);
    }

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
