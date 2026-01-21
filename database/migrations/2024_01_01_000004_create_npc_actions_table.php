<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('npc_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->enum('action_type', ['action', 'bonus_action', 'reaction', 'legendary_action'])->default('action');
            $table->integer('legendary_cost')->nullable(); // For legendary actions
            $table->timestamps();

            $table->index('npc_id');
            $table->index('action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_actions');
    }
};
