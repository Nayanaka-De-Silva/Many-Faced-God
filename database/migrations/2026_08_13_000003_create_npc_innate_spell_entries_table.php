<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores per-entry innate spell data for Innate casting profiles.
     * Cascades on profile delete so no orphans can accumulate.
     *
     * usage is AtWill|PerDay (application-layer enum, not a DB ENUM).
     * uses_per_day is required when usage=PerDay, must be null when AtWill.
     * spell_library_id is nullable for forward-compatibility with the
     * library-of-netheril spell picker (Phase 3/4).
     */
    public function up(): void
    {
        Schema::create('npc_innate_spell_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('casting_profile_id')->constrained('npc_casting_profiles')->cascadeOnDelete();

            $table->string('spell_library_id')->nullable();  // optional library-of-netheril ref
            $table->string('spell_name');                    // display name; always required

            $table->string('usage');                               // AtWill|PerDay
            $table->unsignedTinyInteger('uses_per_day')->nullable(); // required when PerDay; null when AtWill
            $table->string('restriction')->nullable();             // freeform e.g. "self only"
            $table->unsignedTinyInteger('cast_level')->nullable(); // fixed level the spell is cast at

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('casting_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_innate_spell_entries');
    }
};
