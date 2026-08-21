<?php

namespace Tests\Feature;

use App\Support\NpcNotesMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for the NpcNotesMigrator service that handles the data conversion
 * between the legacy notes text column and the npc_notes child table.
 *
 * Migration 002 drops npcs.notes after moving data forward. These tests
 * re-add that column per test so the migrator's forward and reverse paths
 * can be exercised in isolation against the otherwise-complete schema.
 */
class NpcNoteMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** Re-add the notes column that migration 002 dropped, so forward-direction tests work. */
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('npcs', 'notes')) {
            Schema::table('npcs', fn ($t) => $t->text('notes')->nullable());
        }
    }

    public function test_move_notes_to_cards_inserts_one_card_per_npc_with_notes(): void
    {
        $npcId = DB::table('npcs')->insertGetId($this->baseNpcRow(['alignment' => 'Lawful Good']));
        DB::table('npcs')->where('id', $npcId)->update(['notes' => 'Scout the northern pass.']);

        (new NpcNotesMigrator())->moveNotesToCards();

        $cards = DB::table('npc_notes')->where('npc_id', $npcId)->get();
        $this->assertCount(1, $cards);
        $this->assertEquals('General Notes', $cards[0]->title);
        $this->assertEquals('Scout the northern pass.', $cards[0]->description);
        $this->assertEquals(0, $cards[0]->sort_order);
    }

    public function test_move_notes_to_cards_skips_npcs_with_blank_notes(): void
    {
        $npcId = DB::table('npcs')->insertGetId($this->baseNpcRow(['alignment' => 'Neutral']));
        DB::table('npcs')->where('id', $npcId)->update(['notes' => '']);

        (new NpcNotesMigrator())->moveNotesToCards();

        $this->assertEquals(0, DB::table('npc_notes')->where('npc_id', $npcId)->count());
    }

    public function test_move_notes_to_cards_skips_npcs_with_null_notes(): void
    {
        $npcId = DB::table('npcs')->insertGetId($this->baseNpcRow(['alignment' => 'Chaotic Evil']));
        // notes column is NULL by default in the factory; set it explicitly
        DB::table('npcs')->where('id', $npcId)->update(['notes' => null]);

        (new NpcNotesMigrator())->moveNotesToCards();

        $this->assertEquals(0, DB::table('npc_notes')->where('npc_id', $npcId)->count());
    }

    public function test_restore_notes_from_cards_joins_title_and_description(): void
    {
        $npcId = DB::table('npcs')->insertGetId($this->baseNpcRow());
        DB::table('npc_notes')->insert([
            ['npc_id' => $npcId, 'title' => 'Background', 'description' => 'Was a soldier.', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // notes column is present via setUp; restoreNotesFromCards writes back into it
        (new NpcNotesMigrator())->restoreNotesFromCards();

        $text = DB::table('npcs')->where('id', $npcId)->value('notes');
        $this->assertEquals("Background\n\nWas a soldier.", $text);
    }

    public function test_restore_notes_from_cards_joins_multiple_cards_in_sort_order(): void
    {
        $npcId = DB::table('npcs')->insertGetId($this->baseNpcRow());
        DB::table('npc_notes')->insert([
            ['npc_id' => $npcId, 'title' => 'Second', 'description' => 'B', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['npc_id' => $npcId, 'title' => 'First',  'description' => 'A', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        (new NpcNotesMigrator())->restoreNotesFromCards();

        $text = DB::table('npcs')->where('id', $npcId)->value('notes');
        $this->assertEquals("First\n\nA\n\nSecond\n\nB", $text);
    }

    /**
     * Minimal npcs row — only the NOT NULL columns that have no default.
     */
    private function baseNpcRow(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Test NPC',
            'alignment'      => 'True Neutral',
            'strength'       => 10,
            'dexterity'      => 10,
            'constitution'   => 10,
            'intelligence'   => 10,
            'wisdom'         => 10,
            'charisma'       => 10,
            'is_template'    => 0,
            'damage_vulnerabilities' => '[]',
            'damage_resistances'     => '[]',
            'damage_immunities'      => '[]',
            'condition_immunities'   => '[]',
            'senses'         => '[]',
            'languages'      => '[]',
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $overrides);
    }
}
