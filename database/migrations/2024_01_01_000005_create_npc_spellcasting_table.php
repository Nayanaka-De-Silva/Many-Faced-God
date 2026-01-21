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
        Schema::create('npc_spellcasting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
            $table->string('ability'); // e.g., "Intelligence", "Wisdom", "Charisma"
            $table->integer('spell_save_dc')->nullable();
            $table->integer('spell_attack_bonus')->nullable();
            $table->string('caster_level')->nullable();
            $table->text('spellcasting_notes')->nullable();
            $table->json('spells')->nullable(); // Organized by spell level
            $table->timestamps();

            $table->index('npc_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_spellcasting');
    }
};
