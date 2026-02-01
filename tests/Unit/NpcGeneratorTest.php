<?php

namespace Tests\Unit;

use App\Models\Npc;
use App\Services\NpcGenerator;
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
