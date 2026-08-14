<?php

namespace App\Http\Resources\Api\V1;

use App\Models\NpcCastingProfile;
use App\Models\NpcSpellcasting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Collapses both spellcasting systems into one uniform shape.
 *
 * Accepts either an NpcCastingProfile (current system) or an NpcSpellcasting
 * (legacy system). The caller is responsible for choosing which to pass:
 * profiles win when both exist — the legacy row is ignored entirely by NpcResource.
 */
class SpellcastingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof NpcSpellcasting) {
            return $this->fromLegacy();
        }

        return $this->fromProfile();
    }

    /**
     * Serialize a current NpcCastingProfile row.
     */
    private function fromProfile(): array
    {
        /** @var NpcCastingProfile $profile */
        $profile = $this->resource;

        return [
            'type'         => $profile->casting_type,
            'ability'      => $profile->spellcasting_ability,
            'saveDc'       => $profile->save_dc,
            'attackBonus'  => $profile->attack_bonus,
            'casterLevel'  => $profile->caster_level, // tinyint — already int|null
            'sourceClass'  => $profile->source_class,
            'psionics'     => (bool) $profile->psionics,
            'lines'        => $this->buildProfileLines($profile),
            'slots'        => $this->buildProfileSlots($profile),
            'notes'        => null,
        ];
    }

    /**
     * Build the display lines array from the appropriate profile accessor.
     *
     * @return string[]
     */
    private function buildProfileLines(NpcCastingProfile $profile): array
    {
        if ($profile->isInnate()) {
            return $profile->formatted_innate_lines;
        }

        if ($profile->isSpellcasting()) {
            $line = $profile->formatted_slots_line;
            return $line !== null ? [$line] : [];
        }

        if ($profile->isPactMagic()) {
            $line = $profile->formatted_pact_magic_line;
            return $line !== null ? [$line] : [];
        }

        return [];
    }

    /**
     * Build the slots array for Spellcasting profiles; [] for all other types.
     *
     * @return array<int, array{level: int, count: int, spells: string[]}>
     */
    private function buildProfileSlots(NpcCastingProfile $profile): array
    {
        if (!$profile->isSpellcasting()) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'level'  => $row['level'],
                'count'  => $row['slots'],
                'spells' => array_column($row['spells'], 'name'),
            ];
        }, $profile->slots_with_spells);
    }

    /**
     * Synthesize one uniform entry from a legacy NpcSpellcasting row.
     *
     * Legacy caster_level is a string column. If it is numeric ("9") it is cast
     * to int. If it is non-numeric ("9th") it is emitted as null here, and the
     * original text is prepended to "notes" so nothing is silently lost.
     */
    private function fromLegacy(): array
    {
        /** @var NpcSpellcasting $legacy */
        $legacy = $this->resource;

        [$casterLevel, $notes] = $this->resolveLegacyCasterLevel($legacy);

        return [
            'type'        => NpcCastingProfile::TYPE_SPELLCASTING,
            'ability'     => $legacy->ability,
            'saveDc'      => $legacy->spell_save_dc,
            'attackBonus' => $legacy->spell_attack_bonus,
            'casterLevel' => $casterLevel,
            'sourceClass' => null,
            'psionics'    => false,
            'lines'       => $this->buildLegacyLines($legacy),
            'slots'       => [],
            'notes'       => $notes,
        ];
    }

    /**
     * Resolve caster_level from the legacy string column.
     * Numeric strings → int. Non-numeric → null, text prepended to notes.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveLegacyCasterLevel(NpcSpellcasting $legacy): array
    {
        $raw   = $legacy->caster_level;
        $notes = $legacy->spellcasting_notes ?: null;

        if ($raw === null) {
            return [null, $notes];
        }

        if (is_numeric($raw)) {
            return [(int) $raw, $notes];
        }

        // Preserve non-numeric text in notes so it is retrievable
        $notes = $raw . ($notes !== null ? "\n" . $notes : '');

        return [null, $notes];
    }

    /**
     * Build display lines from legacy npc_spellcasting.spells, grouped by level.
     * Spells are stored as [level => [spellName, ...]] where level 0 = cantrips.
     *
     * @return string[]
     */
    private function buildLegacyLines(NpcSpellcasting $legacy): array
    {
        $spells = $legacy->spells ?? [];
        $lines  = [];

        foreach ($spells as $level => $spellNames) {
            if (empty($spellNames)) {
                continue;
            }

            $spellList = implode(', ', (array) $spellNames);

            if ((int) $level === 0) {
                $lines[] = "Cantrips (at will): {$spellList}";
            } else {
                // ordinalLevel() is private; use the public ordinalSuffix() + " level"
                $ordinal = NpcCastingProfile::ordinalSuffix((int) $level) . ' level';
                $lines[] = ucfirst($ordinal) . ': ' . $spellList;
            }
        }

        return $lines;
    }
}
