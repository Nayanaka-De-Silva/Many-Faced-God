<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npc_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npc_id')->constrained('npcs')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['npc_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npc_notes');
    }
};
