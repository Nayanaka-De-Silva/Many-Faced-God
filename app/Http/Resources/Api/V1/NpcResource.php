<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Npc;
use App\Support\ChallengeRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full NPC statblock resource — used by both /npcs and /templates endpoints.
 *
 * IMPORTANT: NpcResource::$folderPathMap must be set by the controller before
 * serializing a collection. The controller calls Folder::buildPathMap() once
 * per request and assigns the result here, avoiding N+1 folder path lookups.
 *
 * @property Npc $resource
 */
class NpcResource extends JsonResource
{
    /**
     * Precomputed id→path map injected by NpcController/TemplateController.
     * Set this before serializing any NpcResource collection.
     *
     * @var array<int, string>|null
     */
    public static ?array $folderPathMap = null;

    public function toArray(Request $request): array
    {
        /** @var Npc $npc */
        $npc = $this->resource;

        return [
            'id'          => $npc->id,
            'sourceRef'   => "mfg:npc:{$npc->id}",
            'name'        => $npc->name,
            'type'        => $npc->npc_type,
            'alignment'   => $npc->alignment,
            'isTemplate'  => (bool) $npc->is_template,
            'folder'      => $this->serializeFolder($npc),

            'challengeRating' => $this->serializeChallengeRating($npc->challenge_rating),
            'proficiencyBonus' => $npc->proficiency_bonus, // stored column verbatim

            'armorClass'  => $npc->armor_class,
            'armorType'   => $npc->armor_type,
            'hitPoints'   => $npc->hit_points,
            'hitDice'     => $npc->hit_dice,
            // hitPointsSuggested only when hit_points is null; null otherwise to avoid needless computation
            'hitPointsSuggested' => $npc->hit_points === null && $npc->hit_dice !== null
                ? Npc::averageHitPoints($npc->hit_dice)
                : null,
            'speed' => $npc->speed,

            'abilities' => [
                'strength'     => $this->serializeAbility($npc->strength,     $npc->strength_modifier),
                'dexterity'    => $this->serializeAbility($npc->dexterity,    $npc->dexterity_modifier),
                'constitution' => $this->serializeAbility($npc->constitution, $npc->constitution_modifier),
                'intelligence' => $this->serializeAbility($npc->intelligence, $npc->intelligence_modifier),
                'wisdom'       => $this->serializeAbility($npc->wisdom,       $npc->wisdom_modifier),
                'charisma'     => $this->serializeAbility($npc->charisma,     $npc->charisma_modifier),
            ],

            // Nullable JSON arrays coalesced to [] — Arena expects arrays, never null
            'savingThrowProficiencies' => $npc->saving_throw_proficiencies ?? [],
            'skillProficiencies'       => $npc->skill_proficiencies ?? [],
            'passivePerception'        => $npc->passive_perception,
            'damageVulnerabilities'    => $npc->damage_vulnerabilities ?? [],
            'damageResistances'        => $npc->damage_resistances ?? [],
            'damageImmunities'         => $npc->damage_immunities ?? [],
            'conditionImmunities'      => $npc->condition_immunities ?? [],

            'senses' => [
                'raw'       => $npc->senses ?? [],
                'formatted' => $npc->formatted_senses,
            ],

            'languages' => $npc->languages ?? [],

            'traits'       => NpcTraitResource::collection($npc->traits),
            'actions'      => NpcActionResource::collection($npc->actions),
            'spellcasting' => $this->serializeSpellcasting($npc),

            'notes'             => $npc->notes,
            'personalityTraits' => $npc->personality_traits,
            'ideals'            => $npc->ideals,
            'bonds'             => $npc->bonds,
            'flaws'             => $npc->flaws,

            'createdAt' => $npc->created_at?->toIso8601String(),
            'updatedAt' => $npc->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build the challengeRating object — always an object, never bare null.
     *
     * CRITICAL: null value must propagate as null xp, NEVER 0.
     * A silent 0 corrupts every DMG difficulty calc in Arena.
     */
    private function serializeChallengeRating(?string $cr): array
    {
        return [
            'value'          => $cr,
            'xp'             => ChallengeRating::xp($cr), // null when cr is null
            'xpIfDangerous'  => $cr === '0' ? ChallengeRating::XP_CR_ZERO_IF_DANGEROUS : null,
        ];
    }

    /**
     * Build one ability score block.
     */
    private function serializeAbility(int $score, int $modifier): array
    {
        return [
            'score'    => $score,
            'modifier' => $modifier,
            'display'  => Npc::formatModifier($modifier),
        ];
    }

    /**
     * Serialize the folder as a compact {id, name, path} block, or null.
     */
    private function serializeFolder(Npc $npc): ?array
    {
        if ($npc->folder_id === null || $npc->folder === null) {
            return null;
        }

        $path = static::$folderPathMap[$npc->folder_id] ?? $npc->folder->name;

        return [
            'id'   => $npc->folder->id,
            'name' => $npc->folder->name,
            'path' => $path,
        ];
    }

    /**
     * Collapse both spellcasting systems into one uniform array.
     *
     * Profiles win when both exist — the legacy npc_spellcasting row is ignored
     * entirely when castingProfiles is non-empty.
     * Empty array [] when neither exists — never null.
     *
     * @return SpellcastingResource[]
     */
    private function serializeSpellcasting(Npc $npc): array
    {
        if ($npc->castingProfiles->isNotEmpty()) {
            return SpellcastingResource::collection($npc->castingProfiles)->resolve();
        }

        if ($npc->spellcasting !== null) {
            return [(new SpellcastingResource($npc->spellcasting))->resolve()];
        }

        return [];
    }
}
