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
        Schema::create('npcs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('npc_type')->nullable(); // e.g., "Medium Humanoid"
            $table->string('alignment')->nullable(); // e.g., "Chaotic Good"

            // Combat Stats
            $table->integer('armor_class')->nullable();
            $table->string('armor_type')->nullable(); // e.g., "Chain Mail"
            $table->integer('hit_points')->nullable();
            $table->string('hit_dice')->nullable(); // e.g., "4d8+8"
            $table->string('speed')->nullable(); // e.g., "30 ft, fly 60 ft"

            // Attributes
            $table->integer('strength')->default(10);
            $table->integer('dexterity')->default(10);
            $table->integer('constitution')->default(10);
            $table->integer('intelligence')->default(10);
            $table->integer('wisdom')->default(10);
            $table->integer('charisma')->default(10);

            // Proficiencies - stored as JSON arrays
            $table->json('saving_throw_proficiencies')->nullable();
            $table->json('skill_proficiencies')->nullable();

            // Damage/Condition modifiers - stored as JSON arrays
            $table->json('damage_vulnerabilities')->nullable();
            $table->json('damage_resistances')->nullable();
            $table->json('damage_immunities')->nullable();
            $table->json('condition_immunities')->nullable();

            // Senses and Languages
            $table->json('senses')->nullable(); // e.g., [{"type": "Darkvision", "range": 60}]
            $table->json('languages')->nullable(); // e.g., ["Common", "Elvish"]

            // Challenge Rating
            $table->string('challenge_rating')->nullable(); // e.g., "1/2", "5"
            $table->integer('proficiency_bonus')->nullable();

            // Organization
            $table->foreignId('folder_id')->nullable()->constrained('folders')->onDelete('set null');
            $table->boolean('is_template')->default(false);

            $table->timestamps();

            $table->index('name');
            $table->index('folder_id');
            $table->index('is_template');
            $table->index('challenge_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npcs');
    }
};
