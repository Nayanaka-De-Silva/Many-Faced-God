<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Handles the data conversion between the legacy `notes` text column
 * and the new `npc_notes` child table. Extracted from the migration so
 * the logic can be unit-tested independently of RefreshDatabase.
 */
class NpcNotesMigrator
{
    /**
     * Copy non-blank `npcs.notes` values into one "General Notes" card each,
     * then clear the column (the migration itself drops the column afterward).
     * Skips NPCs that already have note cards, so a partial rollback + re-migrate
     * of just this migration (leaving npc_notes intact) doesn't duplicate cards.
     */
    public function moveNotesToCards(): void
    {
        DB::table('npcs')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('id')
            ->each(function (object $npc): void {
                $alreadyMigrated = DB::table('npc_notes')->where('npc_id', $npc->id)->exists();

                if ($alreadyMigrated) {
                    return;
                }

                DB::table('npc_notes')->insert([
                    'npc_id'      => $npc->id,
                    'title'       => 'General Notes',
                    'description' => $npc->notes,
                    'sort_order'  => 0,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            });
    }

    /**
     * Reconstruct the `notes` text column from each NPC's note cards,
     * joining cards as "Title\n\nDescription" blocks ordered by sort_order.
     */
    public function restoreNotesFromCards(): void
    {
        DB::table('npcs')->orderBy('id')->each(function (object $npc): void {
            $cards = DB::table('npc_notes')
                ->where('npc_id', $npc->id)
                ->orderBy('sort_order')
                ->get(['title', 'description']);

            if ($cards->isEmpty()) {
                return;
            }

            $text = $cards->map(function (object $card): string {
                return filled($card->description)
                    ? "{$card->title}\n\n{$card->description}"
                    : $card->title;
            })->join("\n\n");

            DB::table('npcs')->where('id', $npc->id)->update(['notes' => $text]);
        });
    }
}
