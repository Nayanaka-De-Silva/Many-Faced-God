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
        Schema::create('npc_traits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->timestamps();

            $table->index('npc_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_traits');
    }
};
