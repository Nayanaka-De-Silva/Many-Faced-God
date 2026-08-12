<?php

namespace Tests\Unit;

use App\Models\NpcInnateSpellEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpcInnateSpellEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_constants_are_correct(): void
    {
        $this->assertSame('AtWill', NpcInnateSpellEntry::USAGE_AT_WILL);
        $this->assertSame('PerDay', NpcInnateSpellEntry::USAGE_PER_DAY);
    }

    public function test_formatted_line_for_at_will_spell(): void
    {
        $entry = NpcInnateSpellEntry::factory()->atWill()->make(['spell_name' => 'Fire Bolt']);

        $this->assertSame('Fire Bolt (at will)', $entry->formatted_line);
    }

    public function test_formatted_line_for_per_day_spell(): void
    {
        $entry = NpcInnateSpellEntry::factory()->perDay(3)->make(['spell_name' => 'Charm Person']);

        $this->assertSame('Charm Person (3/day)', $entry->formatted_line);
    }

    public function test_formatted_line_for_per_day_defaults_to_one_when_uses_not_set(): void
    {
        $entry = NpcInnateSpellEntry::factory()->make([
            'spell_name' => 'Darkness',
            'usage' => NpcInnateSpellEntry::USAGE_PER_DAY,
            'uses_per_day' => null,
        ]);

        $this->assertSame('Darkness (1/day)', $entry->formatted_line);
    }
}
