<?php

namespace Database\Factories;

use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class NpcInnateSpellEntryFactory extends Factory
{
    protected $model = NpcInnateSpellEntry::class;

    /**
     * Default state: an at-will spell entry.
     */
    public function definition(): array
    {
        return [
            'casting_profile_id' => NpcCastingProfile::factory()->innate(),
            'spell_library_id' => null,
            'spell_name' => 'Dancing Lights',
            'usage' => NpcInnateSpellEntry::USAGE_AT_WILL,
            'uses_per_day' => null,
            'restriction' => null,
            'cast_level' => null,
            'sort_order' => 0,
        ];
    }

    /** At-will usage state. */
    public function atWill(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage' => NpcInnateSpellEntry::USAGE_AT_WILL,
            'uses_per_day' => null,
        ]);
    }

    /** Per-day usage state with a configurable count. */
    public function perDay(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'usage' => NpcInnateSpellEntry::USAGE_PER_DAY,
            'uses_per_day' => $count,
        ]);
    }
}
