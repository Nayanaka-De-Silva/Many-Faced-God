<?php

namespace Tests\Unit;

use App\Models\Npc;
use App\Models\NpcNote;
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

    public function test_note_preview_returns_null_when_no_cards(): void
    {
        $npc = new Npc();
        $npc->setRelation('noteCards', collect());

        $this->assertNull($npc->notePreview());
    }

    public function test_note_preview_returns_title_only_when_description_blank(): void
    {
        $note = new NpcNote(['title' => 'Background', 'description' => null]);
        $npc = new Npc();
        $npc->setRelation('noteCards', collect([$note]));

        $this->assertEquals('Background', $npc->notePreview());
    }

    public function test_note_preview_returns_title_dash_first_sentence_of_description(): void
    {
        $note = new NpcNote([
            'title'       => 'Background',
            'description' => 'First detail. Second detail. Third detail should not appear.',
        ]);
        $npc = new Npc();
        $npc->setRelation('noteCards', collect([$note]));

        $this->assertEquals('Background — First detail. Second detail.', $npc->notePreview());
    }

    public function test_note_preview_reads_from_first_card_only(): void
    {
        $first  = new NpcNote(['title' => 'Alpha', 'description' => 'First card.']);
        $second = new NpcNote(['title' => 'Beta',  'description' => 'Second card.']);
        $npc = new Npc();
        $npc->setRelation('noteCards', collect([$first, $second]));

        $preview = $npc->notePreview();
        $this->assertStringContainsString('Alpha', $preview);
        $this->assertStringNotContainsString('Beta', $preview);
    }

    public function test_has_character_notes_detects_populated_fields(): void
    {
        $npc = new Npc([
            'bonds' => 'Protect the temple at all costs.',
        ]);

        $this->assertTrue($npc->hasCharacterNotes());
        $this->assertFalse((new Npc())->hasCharacterNotes());
    }

    public function test_has_loot_vault_reflects_the_vivaldi_vault_id(): void
    {
        $this->assertFalse((new Npc())->hasLootVault());

        $npc = new Npc();
        $npc->vivaldi_vault_id = '5b1e0e8a-0000-4000-8000-000000000000';

        $this->assertTrue($npc->hasLootVault());
    }

    public function test_loot_external_ref_is_stable_and_id_scoped(): void
    {
        $npc = new Npc();
        $npc->id = 42;

        $this->assertEquals('many-faced-god:npc-42', $npc->lootExternalRef());
    }

    public function test_vivaldi_vault_id_is_mass_assignable(): void
    {
        $npc = new Npc(['vivaldi_vault_id' => '5b1e0e8a-0000-4000-8000-000000000000']);

        $this->assertEquals('5b1e0e8a-0000-4000-8000-000000000000', $npc->vivaldi_vault_id);
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
