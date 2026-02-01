<?php

namespace Database\Factories;

use App\Models\Npc;
use App\Models\NpcSpellcasting;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpcSpellcastingFactory extends Factory
{
    protected $model = NpcSpellcasting::class;

    public function definition(): array
    {
        return [
            'npc_id' => Npc::factory(),
            'ability' => fake()->randomElement(['Intelligence', 'Wisdom', 'Charisma']),
            'spell_save_dc' => fake()->numberBetween(10, 18),
            'spell_attack_bonus' => fake()->numberBetween(2, 10),
            'caster_level' => fake()->randomElement(['1st', '3rd', '5th', '7th', '9th']),
            'spellcasting_notes' => 'The creature has the following spells prepared:',
            'spells' => [
                0 => ['Fire Bolt', 'Mage Hand'],
                1 => ['Magic Missile', 'Shield'],
            ],
        ];
    }
}
