<?php

namespace Tests\Feature;

use App\Models\Npc;
use App\Models\Folder;
use App\Models\NpcTrait;
use App\Models\NpcAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use DOMDocument;
use DOMXPath;

class NpcControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_npcs(): void
    {
        $npc = Npc::factory()->create(['name' => 'Test NPC']);

        $response = $this->get(route('npcs.index'));

        $response->assertStatus(200);
        $response->assertSee('Test NPC');
    }

    public function test_index_excludes_templates(): void
    {
        $npc = Npc::factory()->create(['name' => 'Regular NPC', 'is_template' => false]);
        $template = Npc::factory()->template()->create(['name' => 'Template NPC']);

        $response = $this->get(route('npcs.index'));

        $response->assertStatus(200);
        $response->assertSee('Regular NPC');
        $response->assertDontSee('Template NPC');
    }

    public function test_index_can_filter_by_search(): void
    {
        Npc::factory()->create(['name' => 'Goblin King']);
        Npc::factory()->create(['name' => 'Orc Warrior']);

        $response = $this->get(route('npcs.index', ['search' => 'Goblin']));

        $response->assertStatus(200);
        $response->assertSee('Goblin King');
        $response->assertDontSee('Orc Warrior');
    }

    public function test_index_can_filter_by_folder(): void
    {
        $folder = Folder::factory()->create();
        $npcInFolder = Npc::factory()->inFolder($folder)->create(['name' => 'Folder NPC']);
        $npcNotInFolder = Npc::factory()->create(['name' => 'Other NPC']);

        $response = $this->get(route('npcs.index', ['folder_id' => $folder->id]));

        $response->assertStatus(200);
        $response->assertSee('Folder NPC');
        $response->assertDontSee('Other NPC');
    }

    public function test_create_displays_form(): void
    {
        $response = $this->get(route('npcs.create'));

        $response->assertStatus(200);
        $response->assertSee('Create NPC');
    }

    public function test_create_has_template_checkbox_unchecked_by_default(): void
    {
        $response = $this->get(route('npcs.create'));

        $this->assertTemplateCheckboxState($response, checked: false);
    }

    public function test_create_from_templates_dashboard_checks_template_checkbox(): void
    {
        $response = $this->get(route('npcs.create', ['is_template' => 1]));

        $this->assertTemplateCheckboxState($response, checked: true);
    }

    public function test_create_marks_template_links_that_need_hit_point_choice(): void
    {
        $needsChoice = Npc::factory()->template()->create([
            'name' => 'Dice Template',
            'hit_points' => 22,
            'hit_dice' => '3d8+3',
        ]);
        $noChoice = Npc::factory()->template()->create([
            'name' => 'Flat Template',
            'hit_points' => 18,
            'hit_dice' => null,
        ]);

        $response = $this->get(route('npcs.create'));

        $this->assertTemplateLinkChoiceRequirement($response, $needsChoice, true);
        $this->assertTemplateLinkChoiceRequirement($response, $noChoice, false);
    }

    public function test_create_from_template_uses_template_hit_points_when_requested(): void
    {
        $template = Npc::factory()->template()->create([
            'name' => 'Veteran Template',
            'hit_points' => 27,
            'hit_dice' => '3d8+6',
        ]);

        $response = $this->get(route('npcs.create', [
            'from_template' => $template->id,
            'template_hit_points' => Npc::TEMPLATE_HIT_POINT_MODE_TEMPLATE,
        ]));

        $this->assertCreateFormInputValue($response, 'hit_points', '27');
        $this->assertCreateFormInputValue($response, 'hit_dice', '3d8+6');
    }

    public function test_create_from_template_can_prefill_rolled_hit_points(): void
    {
        $template = Npc::factory()->template()->create([
            'name' => 'Scout Template',
            'hit_points' => 99,
            'hit_dice' => '1d4',
        ]);

        $response = $this->get(route('npcs.create', [
            'from_template' => $template->id,
            'template_hit_points' => Npc::TEMPLATE_HIT_POINT_MODE_ROLL,
        ]));

        $rolledHitPoints = (int) $this->getCreateFormInputValue($response, 'hit_points');

        $this->assertGreaterThanOrEqual(1, $rolledHitPoints);
        $this->assertLessThanOrEqual(4, $rolledHitPoints);
        $this->assertCreateFormInputValue($response, 'hit_dice', '1d4');
    }

    public function test_store_creates_npc(): void
    {
        $response = $this->post(route('npcs.store'), [
            'name' => 'New NPC',
            'npc_type' => 'Medium Humanoid',
            'alignment' => 'Neutral Good',
            'strength' => 14,
            'dexterity' => 12,
            'constitution' => 13,
            'intelligence' => 10,
            'wisdom' => 11,
            'charisma' => 8,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', ['name' => 'New NPC']);
    }

    public function test_store_creates_npc_with_traits(): void
    {
        $response = $this->post(route('npcs.store'), [
            'name' => 'NPC With Traits',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'traits' => [
                ['name' => 'Darkvision', 'description' => 'Can see in the dark up to 60 feet.'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', ['name' => 'NPC With Traits']);
        $this->assertDatabaseHas('npc_traits', ['name' => 'Darkvision']);
    }

    public function test_store_creates_npc_with_actions(): void
    {
        $response = $this->post(route('npcs.store'), [
            'name' => 'NPC With Actions',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'actions' => [
                ['name' => 'Longsword', 'description' => '+5 to hit, 1d8+3 slashing', 'action_type' => 'action'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', ['name' => 'NPC With Actions']);
        $this->assertDatabaseHas('npc_actions', ['name' => 'Longsword']);
    }

    public function test_store_creates_npc_with_attack_action(): void
    {
        $response = $this->post(route('npcs.store'), [
            'name' => 'NPC With Attack Action',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'actions' => [
                [
                    'name' => 'Flaming Longsword',
                    'description' => 'The target ignites briefly after the strike.',
                    'action_type' => NpcAction::TYPE_ATTACK,
                    'attack_kind' => 'melee',
                    'attack_range_text' => '5 ft.',
                    'attack_to_hit' => 5,
                    'attack_target' => 'One target',
                    'attack_hit' => '5 (1d10) slashing damage',
                    'attack_hit_2' => '2d6+5 fire damage',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npc_actions', [
            'name' => 'Flaming Longsword',
            'action_type' => NpcAction::TYPE_ATTACK,
            'attack_kind' => 'melee',
            'attack_range_text' => '5 ft.',
            'attack_to_hit' => 5,
            'attack_target' => 'One target',
            'attack_hit' => '5 (1d10) slashing damage',
            'attack_hit_2' => '2d6+5 fire damage',
        ]);
    }

    public function test_store_validates_required_attack_action_fields(): void
    {
        $response = $this->from(route('npcs.create'))->post(route('npcs.store'), [
            'name' => 'Broken Attack Action NPC',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'actions' => [
                [
                    'name' => 'Incomplete Attack',
                    'description' => 'Missing important details.',
                    'action_type' => NpcAction::TYPE_ATTACK,
                ],
            ],
        ]);

        $response->assertRedirect(route('npcs.create'));
        $response->assertSessionHasErrors([
            'actions.0.attack_kind',
            'actions.0.attack_range_text',
            'actions.0.attack_to_hit',
            'actions.0.attack_target',
            'actions.0.attack_hit',
        ]);
    }

    public function test_store_creates_npc_with_notes_and_character_notes(): void
    {
        $response = $this->post(route('npcs.store'), [
            'name' => 'NPC With Notes',
            'notes' => 'Keeps a ledger of every favor owed.',
            'personality_traits' => 'Speaks in clipped, measured sentences.',
            'ideals' => 'Debts should always be repaid.',
            'bonds' => 'Protects the city archives.',
            'flaws' => 'Cannot resist prying into secrets.',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', [
            'name' => 'NPC With Notes',
            'notes' => 'Keeps a ledger of every favor owed.',
            'personality_traits' => 'Speaks in clipped, measured sentences.',
            'ideals' => 'Debts should always be repaid.',
            'bonds' => 'Protects the city archives.',
            'flaws' => 'Cannot resist prying into secrets.',
        ]);
    }

    public function test_store_template_clears_character_note_fields(): void
    {
        $response = $this->post(route('npcs.store'), [
            'name' => 'Template With Notes',
            'is_template' => '1',
            'notes' => 'Use for calculating customs tolls.',
            'personality_traits' => 'Should not persist',
            'ideals' => 'Should not persist',
            'bonds' => 'Should not persist',
            'flaws' => 'Should not persist',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', [
            'name' => 'Template With Notes',
            'is_template' => true,
            'notes' => 'Use for calculating customs tolls.',
            'personality_traits' => null,
            'ideals' => null,
            'bonds' => null,
            'flaws' => null,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('npcs.store'), []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_rolls_hit_points_from_hit_dice(): void
    {
        $response = $this->post(route('npcs.store'), [
            'name' => 'Hit Dice NPC',
            'hit_dice' => '2d8+4',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $response->assertRedirect();
        $npc = Npc::where('name', 'Hit Dice NPC')->first();
        $this->assertNotNull($npc->hit_points);
        $this->assertGreaterThanOrEqual(6, $npc->hit_points);
    }

    public function test_show_displays_npc(): void
    {
        $npc = Npc::factory()->create(['name' => 'Display NPC']);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Display NPC');
    }

    public function test_show_displays_notes_and_character_notes(): void
    {
        $npc = Npc::factory()->create([
            'name' => 'Detailed NPC',
            'notes' => 'Once served in the northern watch.',
            'personality_traits' => 'Never breaks eye contact.',
            'ideals' => 'Duty above comfort.',
            'bonds' => 'His missing captain.',
            'flaws' => 'Suspicious of everyone new.',
        ]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Notes');
        $response->assertSee('Once served in the northern watch.');
        $response->assertSee('Personality Traits');
        $response->assertSee('Never breaks eye contact.');
        $response->assertSee('Ideals');
        $response->assertSee('Duty above comfort.');
        $response->assertSee('Bonds');
        $response->assertSee('His missing captain.');
        $response->assertSee('Flaws');
        $response->assertSee('Suspicious of everyone new.');
    }

    public function test_show_displays_attack_actions(): void
    {
        $npc = Npc::factory()->create(['name' => 'Fire Knight']);
        NpcAction::factory()->attackAction()->create(['npc_id' => $npc->id]);

        $response = $this->get(route('npcs.show', $npc));

        $response->assertStatus(200);
        $response->assertSee('Flaming Longsword');
        $response->assertSee('Melee Weapon Attack: +5, Reach 5 ft., One target');
        $response->assertSee('Hit: 5 (1d10) slashing damage (plus 2d6+5 fire damage)');
        $response->assertSee('The target ignites briefly after the strike.');
    }

    public function test_edit_displays_form(): void
    {
        $npc = Npc::factory()->create(['name' => 'Edit NPC']);

        $response = $this->get(route('npcs.edit', $npc));

        $response->assertStatus(200);
        $response->assertSee('Edit NPC');
    }

    public function test_update_modifies_npc(): void
    {
        $npc = Npc::factory()->create(['name' => 'Old Name']);

        $response = $this->put(route('npcs.update', $npc), [
            'name' => 'New Name',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', ['id' => $npc->id, 'name' => 'New Name']);
    }

    public function test_update_persists_note_fields(): void
    {
        $npc = Npc::factory()->create(['name' => 'Archivist']);

        $response = $this->put(route('npcs.update', $npc), [
            'name' => 'Archivist',
            'notes' => 'Remembers every visitor by voice.',
            'personality_traits' => 'Collects names obsessively.',
            'ideals' => 'Knowledge should outlive empires.',
            'bonds' => 'The sealed royal archive.',
            'flaws' => 'Cannot let a mystery rest.',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', [
            'id' => $npc->id,
            'notes' => 'Remembers every visitor by voice.',
            'personality_traits' => 'Collects names obsessively.',
            'ideals' => 'Knowledge should outlive empires.',
            'bonds' => 'The sealed royal archive.',
            'flaws' => 'Cannot let a mystery rest.',
        ]);
    }

    public function test_update_persists_attack_action_fields(): void
    {
        $npc = Npc::factory()->create(['name' => 'Duellist']);

        $response = $this->put(route('npcs.update', $npc), [
            'name' => 'Duellist',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'actions' => [
                [
                    'name' => 'Precise Shot',
                    'description' => 'A carefully aimed opening volley.',
                    'action_type' => NpcAction::TYPE_ATTACK,
                    'attack_kind' => 'ranged',
                    'attack_range_text' => '30/120 ft.',
                    'attack_to_hit' => 6,
                    'attack_target' => 'One target',
                    'attack_hit' => '7 (1d8+3) piercing damage',
                    'attack_hit_2' => '',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('npc_actions', [
            'npc_id' => $npc->id,
            'name' => 'Precise Shot',
            'action_type' => NpcAction::TYPE_ATTACK,
            'attack_kind' => 'ranged',
            'attack_range_text' => '30/120 ft.',
            'attack_to_hit' => 6,
            'attack_target' => 'One target',
            'attack_hit' => '7 (1d8+3) piercing damage',
            'attack_hit_2' => null,
        ]);
    }

    public function test_destroy_deletes_npc(): void
    {
        $npc = Npc::factory()->create();

        $response = $this->delete(route('npcs.destroy', $npc));

        $response->assertRedirect(route('npcs.index'));
        $this->assertDatabaseMissing('npcs', ['id' => $npc->id]);
    }

    public function test_duplicate_creates_copy(): void
    {
        $npc = Npc::factory()->create(['name' => 'Original']);
        NpcTrait::factory()->create(['npc_id' => $npc->id, 'name' => 'Test Trait']);

        $response = $this->post(route('npcs.duplicate', $npc));

        $response->assertRedirect();
        $this->assertDatabaseHas('npcs', ['name' => 'Original (Copy)']);
        $this->assertEquals(2, NpcTrait::where('name', 'Test Trait')->count());
    }

    public function test_generate_creates_random_npc(): void
    {
        $response = $this->post(route('npcs.generate'));

        $response->assertRedirect();
        $this->assertDatabaseCount('npcs', 1);
    }

    public function test_index_shows_notes_preview_without_later_sentences(): void
    {
        Npc::factory()->create([
            'name' => 'Preview NPC',
            'notes' => 'First note sentence. Second note sentence. Third note sentence.',
        ]);

        $response = $this->get(route('npcs.index'));

        $response->assertStatus(200);
        $response->assertSee('First note sentence. Second note sentence.');
        $response->assertDontSee('Third note sentence.');
    }

    private function assertTemplateCheckboxState(TestResponse $response, bool $checked): void
    {
        $response->assertStatus(200);

        $dom = $this->createDomFromResponse($response);
        $checkbox = (new DOMXPath($dom))->query('//*[@id="is_template"]')->item(0);

        $this->assertNotNull($checkbox, 'Expected the Save as Template checkbox to be present.');
        $this->assertSame(
            $checked,
            $checkbox->hasAttribute('checked'),
            $checked
                ? 'Expected the Save as Template checkbox to be checked.'
                : 'Expected the Save as Template checkbox to be unchecked.'
        );
    }

    private function assertTemplateLinkChoiceRequirement(TestResponse $response, Npc $template, bool $requiresChoice): void
    {
        $dom = $this->createDomFromResponse($response);
        $link = (new DOMXPath($dom))
            ->query(sprintf('//a[@href="%s"]', route('npcs.create', ['from_template' => $template->id])))
            ->item(0);

        $this->assertNotNull($link, 'Expected the template creation link to be present.');
        $this->assertSame(
            $requiresChoice ? 'true' : 'false',
            $link->attributes->getNamedItem('data-template-hit-point-choice-required')?->nodeValue
        );
    }

    private function assertCreateFormInputValue(TestResponse $response, string $fieldId, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $this->getCreateFormInputValue($response, $fieldId));
    }

    private function getCreateFormInputValue(TestResponse $response, string $fieldId): string
    {
        $dom = $this->createDomFromResponse($response);
        $input = (new DOMXPath($dom))->query(sprintf('//*[@id="%s"]', $fieldId))->item(0);

        $this->assertNotNull($input, sprintf('Expected the %s input to be present.', $fieldId));

        return $input->attributes->getNamedItem('value')?->nodeValue ?? '';
    }

    private function createDomFromResponse(TestResponse $response): DOMDocument
    {
        $dom = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);

        $dom->loadHTML($response->getContent());

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        return $dom;
    }
}
