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
            'action_type' => NpcAction::TYPE_ACTION,
            'legendary_cost' => null,
            'attack_kind' => null,
            'attack_range_text' => null,
            'attack_to_hit' => null,
            'attack_target' => null,
            'attack_hit' => null,
            'attack_hit_2' => null,
        ];
    }

    public function bonusAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => NpcAction::TYPE_BONUS_ACTION,
        ]);
    }

    public function reaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => NpcAction::TYPE_REACTION,
        ]);
    }

    public function legendary(int $cost = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => NpcAction::TYPE_LEGENDARY_ACTION,
            'legendary_cost' => $cost,
        ]);
    }

    public function attackAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Flaming Longsword',
            'description' => 'The target ignites briefly after the strike.',
            'action_type' => NpcAction::TYPE_ATTACK,
            'legendary_cost' => null,
            'attack_kind' => 'melee',
            'attack_range_text' => '5 ft.',
            'attack_to_hit' => 5,
            'attack_target' => 'One target',
            'attack_hit' => '5 (1d10) slashing damage',
            'attack_hit_2' => '2d6+5 fire damage',
        ]);
    }
}
