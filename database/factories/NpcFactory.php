<?php

namespace Database\Factories;

use App\Models\Npc;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpcFactory extends Factory
{
    protected $model = Npc::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'npc_type' => fake()->randomElement(['Medium Humanoid', 'Small Humanoid', 'Large Beast', 'Medium Undead']),
            'alignment' => fake()->randomElement(Npc::ALIGNMENTS),
            'notes' => fake()->optional()->paragraph(),
            'personality_traits' => fake()->optional()->sentence(),
            'ideals' => fake()->optional()->sentence(),
            'bonds' => fake()->optional()->sentence(),
            'flaws' => fake()->optional()->sentence(),
            'armor_class' => fake()->numberBetween(10, 20),
            'armor_type' => fake()->optional()->randomElement(['leather armor', 'chain mail', 'plate', 'natural armor']),
            'hit_points' => fake()->numberBetween(10, 100),
            'hit_dice' => fake()->numberBetween(1, 10) . 'd8+' . fake()->numberBetween(0, 20),
            'speed' => '30 ft.',
            'strength' => fake()->numberBetween(8, 20),
            'dexterity' => fake()->numberBetween(8, 20),
            'constitution' => fake()->numberBetween(8, 20),
            'intelligence' => fake()->numberBetween(8, 20),
            'wisdom' => fake()->numberBetween(8, 20),
            'charisma' => fake()->numberBetween(8, 20),
            'saving_throw_proficiencies' => fake()->optional()->randomElements(['Strength', 'Dexterity', 'Constitution'], fake()->numberBetween(0, 2)),
            'skill_proficiencies' => fake()->optional()->randomElements(array_keys(Npc::SKILLS), fake()->numberBetween(0, 4)),
            'damage_vulnerabilities' => [],
            'damage_resistances' => [],
            'damage_immunities' => [],
            'condition_immunities' => [],
            'senses' => [['type' => 'Darkvision', 'range' => 60, 'category' => 'ft']],
            'languages' => ['Common'],
            'challenge_rating' => fake()->randomElement(['0', '1/8', '1/4', '1/2', '1', '2', '3', '4', '5']),
            'proficiency_bonus' => 2,
            'folder_id' => null,
            'is_template' => false,
        ];
    }

    /**
     * Create a template NPC.
     */
    public function template(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_template' => true,
            'personality_traits' => null,
            'ideals' => null,
            'bonds' => null,
            'flaws' => null,
        ]);
    }

    /**
     * Create an NPC in a specific folder.
     */
    public function inFolder(Folder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'folder_id' => $folder->id,
        ]);
    }
}
