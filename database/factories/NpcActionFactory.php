<?php

namespace Database\Factories;

use App\Models\NpcAction;
use App\Models\Npc;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpcActionFactory extends Factory
{
    protected $model = NpcAction::class;

    public function definition(): array
    {
        return [
            'npc_id' => Npc::factory(),
            'name' => fake()->randomElement(['Multiattack', 'Longsword', 'Shortbow', 'Bite', 'Claw']),
            'description' => 'Melee Weapon Attack: +5 to hit, reach 5 ft., one target. Hit: 1d8+3 slashing damage.',
            'action_type' => 'action',
            'legendary_cost' => null,
        ];
    }

    public function bonusAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'bonus_action',
        ]);
    }

    public function reaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'reaction',
        ]);
    }

    public function legendary(int $cost = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'legendary_action',
            'legendary_cost' => $cost,
        ]);
    }
}
