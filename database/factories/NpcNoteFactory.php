<?php

namespace Database\Factories;

use App\Models\NpcNote;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpcNoteFactory extends Factory
{
    protected $model = NpcNote::class;

    public function definition(): array
    {
        return [
            'npc_id'      => null, // caller must supply via create(['npc_id' => ...])
            'title'       => fake()->sentence(3, false),
            'description' => fake()->paragraph(),
            'sort_order'  => 0,
        ];
    }
}
