<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The casting_type column uses a plain string (not a DB ENUM) so
     * application-layer Rule::in() handles the closed-set constraint.
     * Valid values: Innate | Spellcasting | PactMagic.
     *
     * The spells_known_or_prepared column stores both Spellcasting's
     * "spellsKnownOrPrepared" and PactMagic's "spellsKnown" — same
     * spell-reference shape: [{"library_id": string|null, "name": string}].
     */
    public function up(): void
    {
        Schema::create('npc_casting_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_id')->constrained('npcs')->cascadeOnDelete();

            // Discriminator: Innate | Spellcasting | PactMagic
            $table->string('casting_type');

            // Cross-cutting required fields
            $table->string('spellcasting_ability');   // Intelligence|Wisdom|Charisma
            $table->unsignedTinyInteger('save_dc');
            $table->tinyInteger('attack_bonus')->nullable();
            $table->boolean('psionics')->default(false);  // Innate + Spellcasting only
            $table->string('source');                      // required citation string
            $table->boolean('homebrew')->default(false);

            // Spellcasting + PactMagic fields
            $table->unsignedTinyInteger('caster_level')->nullable();
            $table->string('source_class')->nullable();          // Spellcasting only
            $table->json('slots')->nullable();                   // Spellcasting: level→count map (keys 1-9)

            // PactMagic-specific flat slot fields
            $table->unsignedTinyInteger('slot_level')->nullable();
            $table->unsignedTinyInteger('slot_count')->nullable();

            // Innate provenance (Innate only)
            $table->string('race_or_origin')->nullable();

            // Spell-reference lists (Spellcasting + PactMagic)
            $table->json('cantrips')->nullable();
            $table->json('spells_known_or_prepared')->nullable();  // covers both spellsKnownOrPrepared and spellsKnown

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('npc_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_casting_profiles');
    }
};
