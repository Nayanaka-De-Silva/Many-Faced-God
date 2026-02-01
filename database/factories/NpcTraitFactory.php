<?php

namespace Database\Factories;

use App\Models\Npc;
use App\Models\NpcTrait;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpcTraitFactory extends Factory
{
    protected $model = NpcTrait::class;

    public function definition(): array
    {
        return [
            'npc_id' => Npc::factory(),
            'name' => fake()->randomElement(['Darkvision', 'Keen Senses', 'Pack Tactics', 'Brave', 'Fey Ancestry']),
            'description' => fake()->sentence(10),
        ];
    }
}
