<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Bank of Vivaldi vault holding this NPC's loot. One vault per NPC;
     * null until a DM attaches one from the loot panel. Stores the vault's
     * own UUID (as returned by the Bank's API) so both systems can cross-link.
     */
    public function up(): void
    {
        Schema::table('npcs', function (Blueprint $table): void {
            $table->uuid('vivaldi_vault_id')->nullable()->after('folder_id');
            $table->index('vivaldi_vault_id');
        });
    }

    public function down(): void
    {
        Schema::table('npcs', function (Blueprint $table): void {
            $table->dropIndex(['vivaldi_vault_id']);
            $table->dropColumn('vivaldi_vault_id');
        });
    }
};
