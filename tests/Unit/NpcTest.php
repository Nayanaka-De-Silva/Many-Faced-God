<?php

namespace Tests\Unit;

use App\Models\Npc;
use PHPUnit\Framework\TestCase;

class NpcTest extends TestCase
{
    public function test_calculate_modifier_returns_correct_values(): void
    {
        $this->assertEquals(-5, Npc::calculateModifier(1));
        $this->assertEquals(-1, Npc::calculateModifier(8));
        $this->assertEquals(0, Npc::calculateModifier(10));
        $this->assertEquals(0, Npc::calculateModifier(11));
        $this->assertEquals(1, Npc::calculateModifier(12));
        $this->assertEquals(2, Npc::calculateModifier(14));
        $this->assertEquals(5, Npc::calculateModifier(20));
        $this->assertEquals(10, Npc::calculateModifier(30));
    }

    public function test_format_modifier_adds_plus_for_positive(): void
    {
        $this->assertEquals('+0', Npc::formatModifier(0));
        $this->assertEquals('+1', Npc::formatModifier(1));
        $this->assertEquals('+5', Npc::formatModifier(5));
    }

    public function test_format_modifier_shows_negative(): void
    {
        $this->assertEquals('-1', Npc::formatModifier(-1));
        $this->assertEquals('-5', Npc::formatModifier(-5));
    }

    public function test_roll_hit_points_returns_valid_result(): void
    {
        // Roll 1d8 should be between 1 and 8
        $result = Npc::rollHitPoints('1d8');
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(8, $result);

        // Roll 2d6+4 should be between 6 and 16
        $result = Npc::rollHitPoints('2d6+4');
        $this->assertGreaterThanOrEqual(6, $result);
        $this->assertLessThanOrEqual(16, $result);
    }

    public function test_roll_hit_points_handles_negative_modifier(): void
    {
        // Roll 1d8-2 should be at least 1 (minimum)
        $result = Npc::rollHitPoints('1d8-2');
        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function test_roll_hit_points_returns_zero_for_invalid_format(): void
    {
        $this->assertEquals(0, Npc::rollHitPoints('invalid'));
        $this->assertEquals(0, Npc::rollHitPoints(''));
    }

    public function test_skills_constant_has_all_dnd_skills(): void
    {
        $expectedSkills = [
            'Acrobatics', 'Animal Handling', 'Arcana', 'Athletics',
            'Deception', 'History', 'Insight', 'Intimidation',
            'Investigation', 'Medicine', 'Nature', 'Perception',
            'Performance', 'Persuasion', 'Religion', 'Sleight of Hand',
            'Stealth', 'Survival'
        ];

        foreach ($expectedSkills as $skill) {
            $this->assertArrayHasKey($skill, Npc::SKILLS);
        }

        $this->assertCount(18, Npc::SKILLS);
    }

    public function test_damage_types_constant_has_all_types(): void
    {
        $this->assertContains('Bludgeoning', Npc::DAMAGE_TYPES);
        $this->assertContains('Fire', Npc::DAMAGE_TYPES);
        $this->assertContains('Necrotic', Npc::DAMAGE_TYPES);
        $this->assertContains('Radiant', Npc::DAMAGE_TYPES);
        $this->assertCount(16, Npc::DAMAGE_TYPES);
    }

    public function test_conditions_constant_has_all_conditions(): void
    {
        $this->assertContains('Blinded', Npc::CONDITIONS);
        $this->assertContains('Charmed', Npc::CONDITIONS);
        $this->assertContains('Paralyzed', Npc::CONDITIONS);
        $this->assertContains('Exhaustion', Npc::CONDITIONS);
        $this->assertCount(15, Npc::CONDITIONS);
    }

    public function test_alignments_constant_has_all_alignments(): void
    {
        $this->assertContains('Lawful Good', Npc::ALIGNMENTS);
        $this->assertContains('Chaotic Evil', Npc::ALIGNMENTS);
        $this->assertContains('True Neutral', Npc::ALIGNMENTS);
        $this->assertContains('Unaligned', Npc::ALIGNMENTS);
        $this->assertCount(10, Npc::ALIGNMENTS);
    }

    public function test_note_preview_returns_first_two_sentences(): void
    {
        $npc = new Npc([
            'notes' => 'First detail. Second detail. Third detail should not appear.',
        ]);

        $this->assertEquals('First detail. Second detail.', $npc->notePreview());
    }

    public function test_has_character_notes_detects_populated_fields(): void
    {
        $npc = new Npc([
            'bonds' => 'Protect the temple at all costs.',
        ]);

        $this->assertTrue($npc->hasCharacterNotes());
        $this->assertFalse((new Npc())->hasCharacterNotes());
    }

    public function test_sense_categories_constant_has_expected_keys(): void
    {
        $this->assertArrayHasKey('ft', Npc::SENSE_CATEGORIES);
        $this->assertArrayHasKey('dc', Npc::SENSE_CATEGORIES);
        $this->assertArrayHasKey('other', Npc::SENSE_CATEGORIES);
        $this->assertCount(3, Npc::SENSE_CATEGORIES);
        $this->assertEquals('ft.', Npc::SENSE_CATEGORIES['ft']);
        $this->assertEquals('DC', Npc::SENSE_CATEGORIES['dc']);
        $this->assertEquals('Other', Npc::SENSE_CATEGORIES['other']);
    }
}
