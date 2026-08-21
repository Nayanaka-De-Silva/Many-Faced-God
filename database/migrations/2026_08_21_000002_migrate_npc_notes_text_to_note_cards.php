<?php

use App\Support\NpcNotesMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('npcs', 'notes')) {
            return; // already migrated or column never existed
        }

        // Move existing free-text notes into one General Notes card per NPC.
        (new NpcNotesMigrator())->moveNotesToCards();

        Schema::table('npcs', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('npcs', 'notes')) {
            return; // column already present
        }

        Schema::table('npcs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('alignment');
        });

        // Write each NPC's note cards back to the notes column as joined text.
        (new NpcNotesMigrator())->restoreNotesFromCards();
    }
};
