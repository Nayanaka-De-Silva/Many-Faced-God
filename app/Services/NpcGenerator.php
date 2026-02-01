<?php

namespace App\Services;

use App\Models\Npc;
use App\Models\NpcTrait;
use App\Models\NpcAction;

class NpcGenerator
{
    /**
     * Challenge ratings with corresponding proficiency bonuses and XP.
     */
    private const CR_DATA = [
        '0' => ['proficiency' => 2, 'xp' => 0],
        '1/8' => ['proficiency' => 2, 'xp' => 25],
        '1/4' => ['proficiency' => 2, 'xp' => 50],
        '1/2' => ['proficiency' => 2, 'xp' => 100],
        '1' => ['proficiency' => 2, 'xp' => 200],
        '2' => ['proficiency' => 2, 'xp' => 450],
        '3' => ['proficiency' => 2, 'xp' => 700],
        '4' => ['proficiency' => 2, 'xp' => 1100],
        '5' => ['proficiency' => 3, 'xp' => 1800],
        '6' => ['proficiency' => 3, 'xp' => 2300],
        '7' => ['proficiency' => 3, 'xp' => 2900],
        '8' => ['proficiency' => 3, 'xp' => 3900],
        '9' => ['proficiency' => 4, 'xp' => 5000],
        '10' => ['proficiency' => 4, 'xp' => 5900],
    ];

    /**
     * Common NPC types.
     */
    private const NPC_TYPES = [
        'Medium Humanoid',
        'Small Humanoid',
        'Large Giant',
        'Medium Undead',
        'Large Beast',
        'Medium Beast',
        'Small Beast',
        'Medium Monstrosity',
        'Large Monstrosity',
    ];

    /**
     * Common NPC names by type.
     */
    private const NAMES = [
        'humanoid' => [
            'Aldric', 'Brenna', 'Cedric', 'Dara', 'Elric', 'Fiona', 'Gareth', 'Helena',
            'Ivan', 'Jasper', 'Kira', 'Lionel', 'Mira', 'Nolan', 'Ophelia', 'Peter',
        ],
        'beast' => [
            'Shadowfang', 'Ironhide', 'Swiftclaw', 'Darkwing', 'Thornback', 'Stormhoof',
        ],
        'undead' => [
            'The Hollow One', 'Bone Collector', 'Night Whisper', 'Grave Walker',
        ],
    ];

    /**
     * Generate a random NPC.
     */
    public function generate(array $options = []): Npc
    {
        $cr = $options['challenge_rating'] ?? $this->randomChallengeRating();
        $crData = self::CR_DATA[$cr] ?? self::CR_DATA['1'];

        $npcType = $options['npc_type'] ?? $this->randomNpcType();
        $alignment = $options['alignment'] ?? $this->randomAlignment();
        $name = $this->generateName($npcType);

        $attributes = $this->generateAttributes($cr);
        $hitDice = $this->calculateHitDice($cr, $attributes['constitution']);

        $npc = Npc::create([
            'name' => $name,
            'npc_type' => $npcType,
            'alignment' => $alignment,
            'armor_class' => $this->calculateArmorClass($attributes['dexterity'], $cr),
            'armor_type' => $this->randomArmorType($cr),
            'hit_dice' => $hitDice,
            'hit_points' => Npc::rollHitPoints($hitDice),
            'speed' => $this->randomSpeed($npcType),
            'strength' => $attributes['strength'],
            'dexterity' => $attributes['dexterity'],
            'constitution' => $attributes['constitution'],
            'intelligence' => $attributes['intelligence'],
            'wisdom' => $attributes['wisdom'],
            'charisma' => $attributes['charisma'],
            'saving_throw_proficiencies' => $this->randomSavingThrows(),
            'skill_proficiencies' => $this->randomSkills(),
            'damage_vulnerabilities' => [],
            'damage_resistances' => [],
            'damage_immunities' => [],
            'condition_immunities' => [],
            'senses' => $this->randomSenses($npcType),
            'languages' => $this->randomLanguages($npcType),
            'challenge_rating' => $cr,
            'proficiency_bonus' => $crData['proficiency'],
            'is_template' => false,
        ]);

        // Add basic actions
        $this->addBasicActions($npc, $crData['proficiency']);

        return $npc;
    }

    /**
     * Generate random attributes based on CR.
     */
    private function generateAttributes(string $cr): array
    {
        $baseStats = $this->crToBaseStats($cr);
        
        return [
            'strength' => $this->randomStat($baseStats),
            'dexterity' => $this->randomStat($baseStats),
            'constitution' => $this->randomStat($baseStats),
            'intelligence' => $this->randomStat($baseStats - 2),
            'wisdom' => $this->randomStat($baseStats - 2),
            'charisma' => $this->randomStat($baseStats - 4),
        ];
    }

    /**
     * Convert CR to base stat value.
     */
    private function crToBaseStats(string $cr): int
    {
        $numericCr = $this->crToNumeric($cr);
        return min(20, max(8, 10 + (int) ($numericCr * 1.5)));
    }

    /**
     * Convert CR string to numeric value.
     */
    private function crToNumeric(string $cr): float
    {
        if (str_contains($cr, '/')) {
            $parts = explode('/', $cr);
            return (int) $parts[0] / (int) $parts[1];
        }
        return (float) $cr;
    }

    /**
     * Generate a random stat around a base value.
     */
    private function randomStat(int $base): int
    {
        $variance = rand(-4, 4);
        return max(3, min(30, $base + $variance));
    }

    /**
     * Calculate hit dice based on CR.
     */
    private function calculateHitDice(string $cr, int $constitution): string
    {
        $numericCr = $this->crToNumeric($cr);
        $numDice = max(1, (int) ($numericCr * 2) + rand(1, 3));
        $diceSize = 8;
        $conMod = Npc::calculateModifier($constitution);
        $modifier = $numDice * $conMod;

        $sign = $modifier >= 0 ? '+' : '';
        return "{$numDice}d{$diceSize}{$sign}{$modifier}";
    }

    /**
     * Calculate armor class.
     */
    private function calculateArmorClass(int $dexterity, string $cr): int
    {
        $dexMod = Npc::calculateModifier($dexterity);
        $numericCr = $this->crToNumeric($cr);
        $baseAc = 10 + $dexMod + (int) ($numericCr / 2);
        return max(10, min(22, $baseAc + rand(-1, 2)));
    }

    /**
     * Random armor type based on CR.
     */
    private function randomArmorType(string $cr): string
    {
        $numericCr = $this->crToNumeric($cr);
        
        if ($numericCr < 1) {
            return collect(['leather armor', 'natural armor', 'none'])->random();
        } elseif ($numericCr < 5) {
            return collect(['studded leather', 'chain shirt', 'scale mail', 'natural armor'])->random();
        }
        
        return collect(['chain mail', 'plate', 'natural armor', 'half plate'])->random();
    }

    /**
     * Random speed based on NPC type.
     */
    private function randomSpeed(string $npcType): string
    {
        $baseSpeed = str_contains(strtolower($npcType), 'small') ? 25 : 30;
        
        $speeds = ["{$baseSpeed} ft."];
        
        if (str_contains(strtolower($npcType), 'beast') && rand(0, 1)) {
            $speeds[] = 'climb ' . $baseSpeed . ' ft.';
        }
        
        return implode(', ', $speeds);
    }

    /**
     * Generate a random name.
     */
    private function generateName(string $npcType): string
    {
        $type = strtolower($npcType);
        
        if (str_contains($type, 'humanoid')) {
            return collect(self::NAMES['humanoid'])->random();
        } elseif (str_contains($type, 'beast')) {
            return collect(self::NAMES['beast'])->random();
        } elseif (str_contains($type, 'undead')) {
            return collect(self::NAMES['undead'])->random();
        }
        
        return 'Creature ' . rand(1, 999);
    }

    /**
     * Random alignment.
     */
    private function randomAlignment(): string
    {
        return collect(Npc::ALIGNMENTS)->random();
    }

    /**
     * Random NPC type.
     */
    private function randomNpcType(): string
    {
        return collect(self::NPC_TYPES)->random();
    }

    /**
     * Random challenge rating.
     */
    private function randomChallengeRating(): string
    {
        return collect(array_keys(self::CR_DATA))->random();
    }

    /**
     * Random saving throw proficiencies.
     */
    private function randomSavingThrows(): array
    {
        $abilities = ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];
        $numProficiencies = rand(0, 2);
        
        return collect($abilities)->random($numProficiencies)->toArray();
    }

    /**
     * Random skill proficiencies.
     */
    private function randomSkills(): array
    {
        $skills = array_keys(Npc::SKILLS);
        $numSkills = rand(1, 4);
        
        return collect($skills)->random($numSkills)->toArray();
    }

    /**
     * Random senses based on NPC type.
     */
    private function randomSenses(string $npcType): array
    {
        $senses = [];
        
        if (rand(0, 1)) {
            $senses[] = ['type' => 'Darkvision', 'range' => rand(1, 3) * 30];
        }
        
        return $senses;
    }

    /**
     * Random languages based on NPC type.
     */
    private function randomLanguages(string $npcType): array
    {
        if (!str_contains(strtolower($npcType), 'humanoid')) {
            return [];
        }
        
        $languages = ['Common'];
        
        $extraLanguages = ['Elvish', 'Dwarvish', 'Orcish', 'Goblin', 'Draconic', 'Abyssal', 'Infernal'];
        if (rand(0, 1)) {
            $languages[] = collect($extraLanguages)->random();
        }
        
        return $languages;
    }

    /**
     * Add basic actions to NPC.
     */
    private function addBasicActions(Npc $npc, int $proficiencyBonus): void
    {
        $strMod = Npc::formatModifier($npc->strength_modifier + $proficiencyBonus);
        $dexMod = Npc::formatModifier($npc->dexterity_modifier + $proficiencyBonus);
        
        $damage = max(1, (int) ($this->crToNumeric($npc->challenge_rating ?? '1'))) . 'd6';

        // Add a melee attack
        $npc->actions()->create([
            'name' => 'Melee Attack',
            'description' => "Melee Weapon Attack: {$strMod} to hit, reach 5 ft., one target. Hit: {$damage} + {$npc->strength_modifier} slashing damage.",
            'action_type' => 'action',
        ]);

        // Maybe add a ranged attack
        if (rand(0, 1)) {
            $npc->actions()->create([
                'name' => 'Ranged Attack',
                'description' => "Ranged Weapon Attack: {$dexMod} to hit, range 80/320 ft., one target. Hit: {$damage} + {$npc->dexterity_modifier} piercing damage.",
                'action_type' => 'action',
            ]);
        }
    }
}
