<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteActionsTableForUp();

            return;
        }

        Schema::table('npc_actions', function (Blueprint $table) {
            $table->string('attack_kind')->nullable()->after('action_type');
            $table->string('attack_range_text')->nullable()->after('attack_kind');
            $table->integer('attack_to_hit')->nullable()->after('attack_range_text');
            $table->string('attack_target')->nullable()->after('attack_to_hit');
            $table->string('attack_hit')->nullable()->after('attack_target');
            $table->string('attack_hit_2')->nullable()->after('attack_hit');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE npc_actions MODIFY action_type ENUM('action', 'bonus_action', 'reaction', 'legendary_action', 'attack_action') NOT NULL DEFAULT 'action'"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteActionsTableForDown();

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::table('npc_actions')
                ->where('action_type', 'attack_action')
                ->update(['action_type' => 'action']);

            DB::statement(
                "ALTER TABLE npc_actions MODIFY action_type ENUM('action', 'bonus_action', 'reaction', 'legendary_action') NOT NULL DEFAULT 'action'"
            );
        }

        Schema::table('npc_actions', function (Blueprint $table) {
            $table->dropColumn([
                'attack_kind',
                'attack_range_text',
                'attack_to_hit',
                'attack_target',
                'attack_hit',
                'attack_hit_2',
            ]);
        });
    }

    /**
     * Rebuild the actions table for SQLite so tests can use the new action type.
     */
    private function rebuildSqliteActionsTableForUp(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::create('npc_actions_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->string('action_type')->default('action');
            $table->string('attack_kind')->nullable();
            $table->string('attack_range_text')->nullable();
            $table->integer('attack_to_hit')->nullable();
            $table->string('attack_target')->nullable();
            $table->string('attack_hit')->nullable();
            $table->string('attack_hit_2')->nullable();
            $table->integer('legendary_cost')->nullable();
            $table->timestamps();

            $table->index('npc_id');
            $table->index('action_type');
        });

        DB::statement(
            "INSERT INTO npc_actions_temp (id, npc_id, name, description, action_type, attack_kind, attack_range_text, attack_to_hit, attack_target, attack_hit, attack_hit_2, legendary_cost, created_at, updated_at)
             SELECT id, npc_id, name, description, action_type, NULL, NULL, NULL, NULL, NULL, NULL, legendary_cost, created_at, updated_at
             FROM npc_actions"
        );

        Schema::drop('npc_actions');
        DB::statement('ALTER TABLE npc_actions_temp RENAME TO npc_actions');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    /**
     * Rebuild the actions table for SQLite when rolling back.
     */
    private function rebuildSqliteActionsTableForDown(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::create('npc_actions_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->string('action_type')->default('action');
            $table->integer('legendary_cost')->nullable();
            $table->timestamps();

            $table->index('npc_id');
            $table->index('action_type');
        });

        DB::statement(
            "INSERT INTO npc_actions_temp (id, npc_id, name, description, action_type, legendary_cost, created_at, updated_at)
             SELECT id, npc_id, name, description, CASE WHEN action_type = 'attack_action' THEN 'action' ELSE action_type END, legendary_cost, created_at, updated_at
             FROM npc_actions"
        );

        Schema::drop('npc_actions');
        DB::statement('ALTER TABLE npc_actions_temp RENAME TO npc_actions');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
