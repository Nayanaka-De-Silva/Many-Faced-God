<?php

namespace Tests\Unit;

use App\Services\NpcGenerator;
use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NpcGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private NpcGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new NpcGenerator();
    }

    public function test_generate_creates_npc(): void
    {
        $npc = $this->generator->generate();

        $this->assertInstanceOf(Npc::class, $npc);
        $this->assertNotEmpty($npc->name);
        $this->assertFalse($npc->is_template);
    }

    public function test_generate_creates_npc_with_valid_attributes(): void
    {
        $npc = $this->generator->generate();

        $this->assertGreaterThanOrEqual(3, $npc->strength);
        $this->assertLessThanOrEqual(30, $npc->strength);
        $this->assertGreaterThanOrEqual(3, $npc->dexterity);
        $this->assertLessThanOrEqual(30, $npc->dexterity);
    }

    public function test_generate_respects_challenge_rating_option(): void
    {
        $npc = $this->generator->generate(['challenge_rating' => '5']);

        $this->assertEquals('5', $npc->challenge_rating);
    }

    public function test_generate_respects_alignment_option(): void
    {
        $npc = $this->generator->generate(['alignment' => 'Lawful Good']);

        $this->assertEquals('Lawful Good', $npc->alignment);
    }

    public function test_generate_respects_npc_type_option(): void
    {
        $npc = $this->generator->generate(['npc_type' => 'Large Giant']);

        $this->assertEquals('Large Giant', $npc->npc_type);
    }

    public function test_generate_creates_actions(): void
    {
        $npc = $this->generator->generate();

        $this->assertGreaterThanOrEqual(1, $npc->actions->count());
    }

    public function test_generate_sets_proficiency_bonus_based_on_cr(): void
    {
        $npc = $this->generator->generate(['challenge_rating' => '1']);
        $this->assertEquals(2, $npc->proficiency_bonus);

        $npc = $this->generator->generate(['challenge_rating' => '5']);
        $this->assertEquals(3, $npc->proficiency_bonus);

        $npc = $this->generator->generate(['challenge_rating' => '9']);
        $this->assertEquals(4, $npc->proficiency_bonus);
    }

    /**
     * Verifies the bug that existed when CR_DATA only covered CR 0-10:
     * passing CR 11+ previously silently fell back to CR 1 data (proficiency +2).
     * After migration to ChallengeRating::proficiencyBonus(), the correct DMG
     * band values are returned.
     */
    public function test_generate_sets_correct_proficiency_bonus_for_cr_above_10(): void
    {
        // CR 11 falls in the 9–12 band → +4
        $npc = $this->generator->generate(['challenge_rating' => '11']);
        $this->assertEquals(4, $npc->proficiency_bonus);

        // CR 17 falls in the 17–20 band → +6
        $npc = $this->generator->generate(['challenge_rating' => '17']);
        $this->assertEquals(6, $npc->proficiency_bonus);

        // CR 25 falls in the 25–28 band → +8
        $npc = $this->generator->generate(['challenge_rating' => '25']);
        $this->assertEquals(8, $npc->proficiency_bonus);
    }

    /**
     * Proves that random generation stays within the bounded GENERATOR_CR_POOL
     * (CR 0–10 only), preserving the same generation behaviour that existed when
     * CR_DATA covered those 11 values. ChallengeRating now handles higher CRs
     * correctly, but the generator pool is deliberately constrained.
     */
    public function test_random_npc_challenge_rating_stays_within_generator_pool(): void
    {
        $allowedCrs = ['0', '1/8', '1/4', '1/2', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

        for ($i = 0; $i < 20; $i++) {
            $npc = $this->generator->generate();
            $this->assertContains($npc->challenge_rating, $allowedCrs);
        }
    }

    public function test_generate_sets_hit_points(): void
    {
        $npc = $this->generator->generate();

        $this->assertNotNull($npc->hit_points);
        $this->assertGreaterThan(0, $npc->hit_points);
    }

    public function test_generate_sets_armor_class(): void
    {
        $npc = $this->generator->generate();

        $this->assertNotNull($npc->armor_class);
        $this->assertGreaterThanOrEqual(10, $npc->armor_class);
    }
}
