<?php

namespace App\Support;

/**
 * Bank of Vivaldi vocabulary, mirrored from its v1 API contract (issue #71).
 *
 * Kept in one place so the loot controller's request validation, the NPC
 * loot panel's inline form, and the tests all agree on exactly what the
 * service will accept. Follows the App\Support\ChallengeRating pattern:
 * public constants, no constructor.
 */
final class Loot
{
    /**
     * Recognised item categories for an inline item definition.
     */
    public const CATEGORIES = [
        'armor', 'weapon', 'equipment', 'adventuring-gear', 'tool', 'mount',
        'vehicle', 'trade-good', 'trinket', 'wondrous-item', 'potion', 'scroll',
        'ring', 'rod', 'staff', 'wand', 'treasure', 'container',
    ];

    /**
     * Recognised item rarities.
     */
    public const RARITIES = [
        'mundane', 'unknown', 'common', 'uncommon', 'rare',
        'very-rare', 'legendary', 'artifact',
    ];

    /**
     * Transfer modes when adding an existing item (e.g. a compendium entry) to a vault.
     */
    public const TRANSFER_MODES = ['copy', 'move'];
}
