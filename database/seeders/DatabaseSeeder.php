<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Folder;
use App\Models\Npc;
use App\Models\NpcTrait;
use App\Models\NpcAction;
use App\Models\NpcSpellcasting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create folders
        $villains = Folder::create(['name' => 'Villains', 'description' => 'Evil NPCs and antagonists']);
        $allies = Folder::create(['name' => 'Allies', 'description' => 'Friendly NPCs and companions']);
        $merchants = Folder::create(['name' => 'Merchants', 'description' => 'Shop keepers and traders']);
        $guards = Folder::create(['name' => 'Guards & Soldiers', 'description' => 'Military and law enforcement']);

        // Create some subfolders
        $bbeg = Folder::create(['name' => 'Big Bad Evil Guys', 'parent_id' => $villains->id]);

        // Create a template: Generic Guard
        $guardTemplate = Npc::create([
            'name' => 'Generic Guard',
            'npc_type' => 'Medium Humanoid (any race)',
            'alignment' => 'Lawful Neutral',
            'armor_class' => 16,
            'armor_type' => 'chain shirt, shield',
            'hit_points' => 11,
            'hit_dice' => '2d8+2',
            'speed' => '30 ft.',
            'strength' => 13,
            'dexterity' => 12,
            'constitution' => 12,
            'intelligence' => 10,
            'wisdom' => 11,
            'charisma' => 10,
            'skill_proficiencies' => ['Perception'],
            'languages' => ['Common'],
            'challenge_rating' => '1/8',
            'proficiency_bonus' => 2,
            'is_template' => true,
        ]);

        NpcAction::create([
            'npc_id' => $guardTemplate->id,
            'name' => 'Spear',
            'description' => 'Melee or Ranged Weapon Attack: +3 to hit, reach 5 ft. or range 20/60 ft., one target. Hit: 4 (1d6 + 1) piercing damage, or 5 (1d8 + 1) piercing damage if used with two hands to make a melee attack.',
            'action_type' => 'action',
        ]);

        // Create sample NPC: Goblin Boss
        $goblinBoss = Npc::create([
            'name' => 'Grik the Goblin Boss',
            'npc_type' => 'Small Humanoid (goblinoid)',
            'alignment' => 'Neutral Evil',
            'armor_class' => 17,
            'armor_type' => 'chain shirt, shield',
            'hit_points' => 21,
            'hit_dice' => '6d6',
            'speed' => '30 ft.',
            'strength' => 10,
            'dexterity' => 14,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 8,
            'charisma' => 10,
            'skill_proficiencies' => ['Stealth'],
            'senses' => [['type' => 'Darkvision', 'range' => 60, 'category' => 'ft']],
            'languages' => ['Common', 'Goblin'],
            'challenge_rating' => '1',
            'proficiency_bonus' => 2,
            'folder_id' => $villains->id,
            'is_template' => false,
        ]);

        NpcTrait::create([
            'npc_id' => $goblinBoss->id,
            'name' => 'Nimble Escape',
            'description' => 'The goblin can take the Disengage or Hide action as a bonus action on each of its turns.',
        ]);

        NpcAction::create([
            'npc_id' => $goblinBoss->id,
            'name' => 'Multiattack',
            'description' => 'The goblin makes two attacks with its scimitar. The second attack has disadvantage.',
            'action_type' => 'action',
        ]);

        NpcAction::create([
            'npc_id' => $goblinBoss->id,
            'name' => 'Scimitar',
            'description' => 'Melee Weapon Attack: +4 to hit, reach 5 ft., one target. Hit: 5 (1d6 + 2) slashing damage.',
            'action_type' => 'action',
        ]);

        NpcAction::create([
            'npc_id' => $goblinBoss->id,
            'name' => 'Redirect Attack',
            'description' => "When a creature the goblin can see targets it with an attack, the goblin chooses another goblin within 5 feet of it. The two goblins swap places, and the chosen goblin becomes the target instead.",
            'action_type' => 'reaction',
        ]);

        // Create sample NPC: Friendly Innkeeper
        $innkeeper = Npc::create([
            'name' => 'Martha Goodbarrel',
            'npc_type' => 'Medium Humanoid (halfling)',
            'alignment' => 'Neutral Good',
            'armor_class' => 10,
            'hit_points' => 9,
            'hit_dice' => '2d8',
            'speed' => '25 ft.',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 12,
            'wisdom' => 14,
            'charisma' => 16,
            'skill_proficiencies' => ['Insight', 'Persuasion'],
            'languages' => ['Common', 'Halfling'],
            'challenge_rating' => '0',
            'proficiency_bonus' => 2,
            'folder_id' => $merchants->id,
            'is_template' => false,
        ]);

        NpcTrait::create([
            'npc_id' => $innkeeper->id,
            'name' => 'Brave',
            'description' => 'Martha has advantage on saving throws against being frightened.',
        ]);

        NpcTrait::create([
            'npc_id' => $innkeeper->id,
            'name' => 'Local Knowledge',
            'description' => "Martha knows everyone in town and can provide information about local rumors, history, and notable figures.",
        ]);

        // Create sample NPC: Archmage Villain
        $archmage = Npc::create([
            'name' => 'Malachar the Undying',
            'npc_type' => 'Medium Humanoid (human)',
            'alignment' => 'Neutral Evil',
            'armor_class' => 12,
            'armor_type' => '15 with mage armor',
            'hit_points' => 99,
            'hit_dice' => '18d8+18',
            'speed' => '30 ft.',
            'strength' => 10,
            'dexterity' => 14,
            'constitution' => 12,
            'intelligence' => 20,
            'wisdom' => 15,
            'charisma' => 16,
            'saving_throw_proficiencies' => ['Intelligence', 'Wisdom'],
            'skill_proficiencies' => ['Arcana', 'History'],
            'damage_resistances' => ['Necrotic'],
            'senses' => [['type' => 'Darkvision', 'range' => 120, 'category' => 'ft']],
            'languages' => ['Common', 'Draconic', 'Abyssal', 'Infernal'],
            'challenge_rating' => '12',
            'proficiency_bonus' => 4,
            'folder_id' => $bbeg->id,
            'is_template' => false,
        ]);

        NpcTrait::create([
            'npc_id' => $archmage->id,
            'name' => 'Magic Resistance',
            'description' => 'Malachar has advantage on saving throws against spells and other magical effects.',
        ]);

        NpcSpellcasting::create([
            'npc_id' => $archmage->id,
            'ability' => 'Intelligence',
            'spell_save_dc' => 17,
            'spell_attack_bonus' => 9,
            'caster_level' => '18th',
            'spellcasting_notes' => 'Malachar can cast disguise self and invisibility at will.',
            'spells' => [
                0 => ['Fire Bolt', 'Light', 'Mage Hand', 'Prestidigitation', 'Shocking Grasp'],
                1 => ['Detect Magic', 'Mage Armor', 'Magic Missile', 'Shield'],
                2 => ['Mirror Image', 'Misty Step', 'Suggestion'],
                3 => ['Counterspell', 'Fly', 'Lightning Bolt'],
                4 => ['Banishment', 'Fire Shield', 'Stoneskin'],
                5 => ['Cone of Cold', 'Scrying', 'Wall of Force'],
                6 => ['Globe of Invulnerability'],
                7 => ['Teleport'],
                8 => ['Mind Blank'],
                9 => ['Time Stop'],
            ],
        ]);

        NpcAction::create([
            'npc_id' => $archmage->id,
            'name' => 'Dagger',
            'description' => 'Melee or Ranged Weapon Attack: +6 to hit, reach 5 ft. or range 20/60 ft., one target. Hit: 4 (1d4 + 2) piercing damage.',
            'action_type' => 'action',
        ]);

        // Create some random NPCs
        Npc::factory()->count(5)->create(['folder_id' => $guards->id]);
        Npc::factory()->count(3)->create(['folder_id' => $allies->id]);
    }
}
