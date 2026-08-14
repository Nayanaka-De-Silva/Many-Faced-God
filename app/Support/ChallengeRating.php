<?php

namespace App\Support;

/**
 * Single source of truth for all Challenge Rating lookups.
 *
 * Mirrors the pattern of App\Models\Npc: public constants + static helpers,
 * no constructor. All methods return null for null or unrecognised input —
 * never a default or fallback value.
 */
final class ChallengeRating
{
    /**
     * All 31 canonical D&D 5e CRs in ascending order.
     * Index position = sort rank (used by sortRank() and orderByRankSql()).
     */
    public const ORDERED = [
        '0', '1/8', '1/4', '1/2',
        '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
        '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
        '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
    ];

    /**
     * CR → XP values from the DMG "Experience Points by Challenge Rating" table.
     *
     * CR 0 is worth 0 XP normally. Use XP_CR_ZERO_IF_DANGEROUS when the
     * creature has a dangerous trait (the add-combatant dialog should prompt
     * the user when CR 0 is selected).
     */
    private const XP = [
        '0'   => 0,
        '1/8' => 25,
        '1/4' => 50,
        '1/2' => 100,
        '1'   => 200,
        '2'   => 450,
        '3'   => 700,
        '4'   => 1100,
        '5'   => 1800,
        '6'   => 2300,
        '7'   => 2900,
        '8'   => 3900,
        '9'   => 5000,
        '10'  => 5900,
        '11'  => 7200,
        '12'  => 8400,
        '13'  => 10000,
        '14'  => 11500,
        '15'  => 13000,
        '16'  => 15000,
        '17'  => 18000,
        '18'  => 20000,
        '19'  => 22000,
        '20'  => 25000,
        '21'  => 33000,
        '22'  => 41000,
        '23'  => 50000,
        '24'  => 62000,
        '25'  => 75000,
        '26'  => 90000,
        '27'  => 105000,
        '28'  => 120000,
        '29'  => 135000,
        '30'  => 155000,
    ];

    /** XP awarded for a CR 0 creature that poses a genuine threat. */
    public const XP_CR_ZERO_IF_DANGEROUS = 10;

    /** Decimal-string representations of the three fractional CRs. */
    private const DECIMAL_TO_FRACTION = [
        '0.125' => '1/8',
        '0.25'  => '1/4',
        '0.5'   => '1/2',
    ];

    // ── public API ──────────────────────────────────────────────────────────

    /** Returns true only for strings present in ORDERED. */
    public static function isValid(?string $cr): bool
    {
        if ($cr === null) {
            return false;
        }

        return in_array($cr, self::ORDERED, strict: true);
    }

    /**
     * XP for the given CR, or null if the input is null or not a recognised CR.
     *
     * IMPORTANT: never returns a fallback — callers must handle null explicitly.
     */
    public static function xp(?string $cr): ?int
    {
        if ($cr === null || !self::isValid($cr)) {
            return null;
        }

        return self::XP[$cr];
    }

    /**
     * Zero-based sort rank (index in ORDERED).
     * Returns null for null or unrecognised input — usable in ORDER BY expressions.
     */
    public static function sortRank(?string $cr): ?int
    {
        if ($cr === null) {
            return null;
        }

        $rank = array_search($cr, self::ORDERED, strict: true);

        return $rank !== false ? $rank : null;
    }

    /**
     * Trims whitespace and converts decimal fraction notation to the canonical
     * D&D string: "0.5" → "1/2", "0.25" → "1/4", "0.125" → "1/8".
     * Unrecognised input is returned trimmed. Null stays null.
     */
    public static function normalize(?string $cr): ?string
    {
        if ($cr === null) {
            return null;
        }

        $trimmed = trim($cr);

        return self::DECIMAL_TO_FRACTION[$trimmed] ?? $trimmed;
    }

    /**
     * Numeric (float) representation of the CR, e.g. '1/2' → 0.5, '5' → 5.0.
     * Returns null for null or unrecognised input.
     */
    public static function numericValue(?string $cr): ?float
    {
        if ($cr === null || !self::isValid($cr)) {
            return null;
        }

        if (str_contains($cr, '/')) {
            [$numerator, $denominator] = explode('/', $cr);

            return (int) $numerator / (int) $denominator;
        }

        return (float) $cr;
    }

    /**
     * DMG proficiency bonus for the given CR, using the standard bands:
     *   CR 0–4 → +2 | 5–8 → +3 | 9–12 → +4 | 13–16 → +5 |
     *   17–20 → +6  | 21–24 → +7 | 25–28 → +8 | 29–30 → +9
     *
     * Returns null for null or unrecognised input.
     */
    public static function proficiencyBonus(?string $cr): ?int
    {
        $numeric = self::numericValue($cr);

        if ($numeric === null) {
            return null;
        }

        return match (true) {
            $numeric <= 4  => 2,
            $numeric <= 8  => 3,
            $numeric <= 12 => 4,
            $numeric <= 16 => 5,
            $numeric <= 20 => 6,
            $numeric <= 24 => 7,
            $numeric <= 28 => 8,
            default        => 9, // CR 29–30
        };
    }

    /**
     * A SQL CASE expression that maps challenge_rating strings to their sort rank,
     * suitable for use with Eloquent's orderByRaw().
     *
     * Generated from self::ORDERED so it is never out of sync with the constant.
     * Unknown / NULL values fall to ELSE 9999, placing them last in ASC order.
     *
     * Usage: Npc::orderByRaw(ChallengeRating::orderByRankSql())
     */
    public static function orderByRankSql(): string
    {
        $cases = [];

        foreach (self::ORDERED as $rank => $cr) {
            $cases[] = "WHEN '{$cr}' THEN {$rank}";
        }

        return 'CASE challenge_rating ' . implode(' ', $cases) . ' ELSE 9999 END';
    }
}
