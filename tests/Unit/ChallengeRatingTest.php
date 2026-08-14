<?php

namespace Tests\Unit;

use App\Support\ChallengeRating;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChallengeRatingTest extends TestCase
{
    // ── xp() ─────────────────────────────────────────────────────────────────

    /** The single most load-bearing assertion: null input MUST return null, never 0 or a fallback. */
    public function test_xp_returns_null_for_null_cr(): void
    {
        $this->assertNull(ChallengeRating::xp(null));
    }

    public function test_xp_returns_null_for_garbage_cr(): void
    {
        $this->assertNull(ChallengeRating::xp('garbage'));
    }

    public function test_xp_returns_null_for_non_standard_fraction(): void
    {
        // '11/2' is not a real D&D CR — must return null, not partially parse it.
        $this->assertNull(ChallengeRating::xp('11/2'));
    }

    public function test_xp_returns_zero_for_cr_zero(): void
    {
        $this->assertSame(0, ChallengeRating::xp('0'));
    }

    public function test_xp_cr_zero_dangerous_constant_is_ten(): void
    {
        $this->assertSame(10, ChallengeRating::XP_CR_ZERO_IF_DANGEROUS);
    }

    #[DataProvider('cr_xp_provider')]
    public function test_xp_matches_dmg_table_for_all_31_crs(string $cr, int $expectedXp): void
    {
        $this->assertSame($expectedXp, ChallengeRating::xp($cr));
    }

    public static function cr_xp_provider(): array
    {
        return [
            'CR 0'  => ['0',    0],
            'CR 1/8'  => ['1/8',  25],
            'CR 1/4'  => ['1/4',  50],
            'CR 1/2'  => ['1/2',  100],
            'CR 1'  => ['1',    200],
            'CR 2'  => ['2',    450],
            'CR 3'  => ['3',    700],
            'CR 4'  => ['4',    1100],
            'CR 5'  => ['5',    1800],
            'CR 6'  => ['6',    2300],
            'CR 7'  => ['7',    2900],
            'CR 8'  => ['8',    3900],
            'CR 9'  => ['9',    5000],
            'CR 10' => ['10',   5900],
            'CR 11' => ['11',   7200],
            'CR 12' => ['12',   8400],
            'CR 13' => ['13',   10000],
            'CR 14' => ['14',   11500],
            'CR 15' => ['15',   13000],
            'CR 16' => ['16',   15000],
            'CR 17' => ['17',   18000],
            'CR 18' => ['18',   20000],
            'CR 19' => ['19',   22000],
            'CR 20' => ['20',   25000],
            'CR 21' => ['21',   33000],
            'CR 22' => ['22',   41000],
            'CR 23' => ['23',   50000],
            'CR 24' => ['24',   62000],
            'CR 25' => ['25',   75000],
            'CR 26' => ['26',   90000],
            'CR 27' => ['27',   105000],
            'CR 28' => ['28',   120000],
            'CR 29' => ['29',   135000],
            'CR 30' => ['30',   155000],
        ];
    }

    // ── sortRank() ────────────────────────────────────────────────────────────

    public function test_sort_rank_returns_null_for_null_cr(): void
    {
        $this->assertNull(ChallengeRating::sortRank(null));
    }

    public function test_sort_rank_returns_null_for_invalid_cr(): void
    {
        $this->assertNull(ChallengeRating::sortRank('garbage'));
    }

    /**
     * Proves the lexical-sort bug is fixed: plain string comparison of '1/8' vs
     * '1/2' vs '2' vs '10' gives the wrong order. Rank integers must be strictly
     * ascending with CR value.
     */
    public function test_sort_rank_is_ascending_and_not_lexical(): void
    {
        $this->assertLessThan(
            ChallengeRating::sortRank('1/2'),
            ChallengeRating::sortRank('1/8'),
            '1/8 must rank below 1/2'
        );
        $this->assertLessThan(
            ChallengeRating::sortRank('2'),
            ChallengeRating::sortRank('1/2'),
            '1/2 must rank below 2'
        );
        $this->assertLessThan(
            ChallengeRating::sortRank('10'),
            ChallengeRating::sortRank('2'),
            '2 must rank below 10'
        );
    }

    // ── normalize() ──────────────────────────────────────────────────────────

    public function test_normalize_returns_null_for_null_input(): void
    {
        $this->assertNull(ChallengeRating::normalize(null));
    }

    public function test_normalize_converts_decimal_half_to_fraction(): void
    {
        $this->assertSame('1/2', ChallengeRating::normalize('0.5'));
    }

    public function test_normalize_converts_decimal_quarter_to_fraction(): void
    {
        $this->assertSame('1/4', ChallengeRating::normalize('0.25'));
    }

    public function test_normalize_converts_decimal_eighth_to_fraction(): void
    {
        $this->assertSame('1/8', ChallengeRating::normalize('0.125'));
    }

    public function test_normalize_trims_whitespace(): void
    {
        $this->assertSame('5', ChallengeRating::normalize(' 5 '));
    }

    public function test_normalize_passes_unrecognized_input_through_trimmed(): void
    {
        $this->assertSame('garbage', ChallengeRating::normalize('  garbage  '));
    }

    // ── proficiencyBonus() ───────────────────────────────────────────────────

    public function test_proficiency_bonus_returns_null_for_null_cr(): void
    {
        $this->assertNull(ChallengeRating::proficiencyBonus(null));
    }

    public function test_proficiency_bonus_returns_null_for_invalid_cr(): void
    {
        $this->assertNull(ChallengeRating::proficiencyBonus('garbage'));
    }

    public function test_proficiency_bonus_cr_0_to_4_is_2(): void
    {
        foreach (['0', '1/8', '1/4', '1/2', '1', '2', '3', '4'] as $cr) {
            $this->assertSame(2, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +2");
        }
    }

    public function test_proficiency_bonus_cr_5_to_8_is_3(): void
    {
        foreach (['5', '6', '7', '8'] as $cr) {
            $this->assertSame(3, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +3");
        }
    }

    public function test_proficiency_bonus_cr_9_to_12_is_4(): void
    {
        foreach (['9', '10', '11', '12'] as $cr) {
            $this->assertSame(4, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +4");
        }
    }

    public function test_proficiency_bonus_cr_13_to_16_is_5(): void
    {
        foreach (['13', '14', '15', '16'] as $cr) {
            $this->assertSame(5, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +5");
        }
    }

    public function test_proficiency_bonus_cr_17_to_20_is_6(): void
    {
        foreach (['17', '18', '19', '20'] as $cr) {
            $this->assertSame(6, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +6");
        }
    }

    public function test_proficiency_bonus_cr_21_to_24_is_7(): void
    {
        foreach (['21', '22', '23', '24'] as $cr) {
            $this->assertSame(7, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +7");
        }
    }

    public function test_proficiency_bonus_cr_25_to_28_is_8(): void
    {
        foreach (['25', '26', '27', '28'] as $cr) {
            $this->assertSame(8, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +8");
        }
    }

    public function test_proficiency_bonus_cr_29_to_30_is_9(): void
    {
        foreach (['29', '30'] as $cr) {
            $this->assertSame(9, ChallengeRating::proficiencyBonus($cr), "CR {$cr} should be +9");
        }
    }

    // ── isValid() ────────────────────────────────────────────────────────────

    public function test_is_valid_returns_false_for_null(): void
    {
        $this->assertFalse(ChallengeRating::isValid(null));
    }

    public function test_is_valid_returns_false_for_garbage(): void
    {
        $this->assertFalse(ChallengeRating::isValid('garbage'));
    }

    public function test_is_valid_returns_true_for_all_ordered_entries(): void
    {
        foreach (ChallengeRating::ORDERED as $cr) {
            $this->assertTrue(ChallengeRating::isValid($cr), "CR '{$cr}' should be valid");
        }
    }

    // ── orderByRankSql() ─────────────────────────────────────────────────────

    public function test_order_by_rank_sql_returns_non_empty_case_expression(): void
    {
        $sql = ChallengeRating::orderByRankSql();
        $this->assertIsString($sql);
        $this->assertNotEmpty($sql);
        $this->assertStringContainsStringIgnoringCase('CASE', $sql);
        $this->assertStringContainsStringIgnoringCase('WHEN', $sql);
        $this->assertStringContainsStringIgnoringCase('END', $sql);
    }
}
