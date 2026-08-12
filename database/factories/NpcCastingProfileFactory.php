<?php

namespace Database\Factories;

use App\Models\Npc;
use App\Models\NpcCastingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpcCastingProfileFactory extends Factory
{
    protected $model = NpcCastingProfile::class;

    /**
     * Default state: a minimal Innate profile.
     */
    public function definition(): array
    {
        return [
            'npc_id' => Npc::factory(),
            'casting_type' => NpcCastingProfile::TYPE_INNATE,
            'spellcasting_ability' => 'Charisma',
            'save_dc' => 14,
            'attack_bonus' => null,
            'psionics' => false,
            'source' => 'Monster Manual',
            'homebrew' => false,
            'caster_level' => null,
            'source_class' => null,
            'slots' => null,
            'slot_level' => null,
            'slot_count' => null,
            'race_or_origin' => null,
            'cantrips' => null,
            'spells_known_or_prepared' => null,
            'sort_order' => 0,
        ];
    }

    /** Innate casting profile state. */
    public function innate(): static
    {
        return $this->state(fn (array $attributes) => [
            'casting_type' => NpcCastingProfile::TYPE_INNATE,
            'spellcasting_ability' => 'Charisma',
            'save_dc' => 14,
            'attack_bonus' => null,
            'psionics' => false,
            'race_or_origin' => 'Drow Magic',
            // type-exclusive fields nulled
            'caster_level' => null,
            'source_class' => null,
            'slots' => null,
            'slot_level' => null,
            'slot_count' => null,
            'cantrips' => null,
            'spells_known_or_prepared' => null,
        ]);
    }

    /** Spellcasting casting profile state (Wizard archetype). */
    public function spellcasting(): static
    {
        return $this->state(fn (array $attributes) => [
            'casting_type' => NpcCastingProfile::TYPE_SPELLCASTING,
            'spellcasting_ability' => 'Intelligence',
            'save_dc' => 15,
            'attack_bonus' => 7,
            'psionics' => false,
            'source' => 'Monster Manual, Archmage',
            'caster_level' => 18,
            'source_class' => 'Wizard',
            'slots' => [1 => 4, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 1, 7 => 1, 8 => 1, 9 => 1],
            'cantrips' => [['library_id' => null, 'name' => 'Fire Bolt']],
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'Magic Missile']],
            // type-exclusive fields nulled
            'slot_level' => null,
            'slot_count' => null,
            'race_or_origin' => null,
        ]);
    }

    /** Pact Magic casting profile state (Warlock archetype). */
    public function pactMagic(): static
    {
        return $this->state(fn (array $attributes) => [
            'casting_type' => NpcCastingProfile::TYPE_PACT_MAGIC,
            'spellcasting_ability' => 'Charisma',
            'save_dc' => 14,
            'attack_bonus' => 6,
            'source' => 'Monster Manual, Warlock',
            'caster_level' => 5,
            'slot_level' => 3,
            'slot_count' => 2,
            'cantrips' => [['library_id' => null, 'name' => 'Eldritch Blast']],
            'spells_known_or_prepared' => [['library_id' => null, 'name' => 'Hex']],
            // type-exclusive fields nulled
            'slots' => null,
            'source_class' => null,
            'race_or_origin' => null,
            'psionics' => false,
        ]);
    }
}
