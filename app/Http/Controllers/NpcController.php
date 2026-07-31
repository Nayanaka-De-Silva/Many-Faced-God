<?php

namespace App\Http\Controllers;

use App\Models\Npc;
use App\Models\Folder;
use App\Models\NpcTrait;
use App\Models\NpcAction;
use App\Models\NpcSpellcasting;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class NpcController extends Controller
{
    /**
     * Display a listing of NPCs.
     */
    public function index(Request $request): View
    {
        $query = Npc::with(['folder', 'traits', 'actions'])
            ->npcs()
            ->orderBy('name');

        // Search filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Folder filter
        if ($request->filled('folder_id')) {
            if ($request->folder_id === 'root') {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $request->folder_id);
            }
        }

        // Challenge rating filter
        if ($request->filled('challenge_rating')) {
            $query->byChallengeRating($request->challenge_rating);
        }

        // Alignment filter
        if ($request->filled('alignment')) {
            $query->where('alignment', $request->alignment);
        }

        $npcs = $query->paginate(25);
        $folders = Folder::treeOptions();

        return view('npcs.index', compact('npcs', 'folders'));
    }

    /**
     * Show the form for creating a new NPC.
     */
    public function create(Request $request): View
    {
        $folders = Folder::treeOptions();
        $templates = Npc::templates()->orderBy('name')->get();
        $sourceNpc = null;
        $defaultIsTemplate = $request->boolean('is_template');
        $templateHitPointMode = $request->query('template_hit_points');

        if (! in_array($templateHitPointMode, Npc::TEMPLATE_HIT_POINT_MODES, true)) {
            $templateHitPointMode = null;
        }

        // If creating from template or existing NPC
        if ($request->filled('from_template')) {
            $sourceNpc = Npc::with(['traits', 'actions', 'spellcasting'])
                ->find($request->from_template);

            $sourceNpc?->applyTemplateHitPointMode($templateHitPointMode);
        } elseif ($request->filled('from_npc')) {
            $sourceNpc = Npc::with(['traits', 'actions', 'spellcasting'])
                ->find($request->from_npc);
        }

        return view('npcs.create', compact('folders', 'templates', 'sourceNpc', 'defaultIsTemplate'));
    }

    /**
     * Store a newly created NPC.
     */
    public function store(Request $request): RedirectResponse|Response
    {
        $validator = $this->makeNpcValidator($request);

        if ($validator->fails()) {
            return $this->renderCreateValidationFailure($request, $validator);
        }

        $validated = $this->sanitizeNpcData($validator->validated());

        // Roll hit points if hit dice provided but no HP
        if (empty($validated['hit_points']) && !empty($validated['hit_dice'])) {
            $validated['hit_points'] = Npc::rollHitPoints($validated['hit_dice']);
        }

        $npc = Npc::create($validated);

        // Create traits
        if (!empty($validated['traits'])) {
            foreach ($validated['traits'] as $trait) {
                $npc->traits()->create($trait);
            }
        }

        // Create actions
        if (!empty($validated['actions'])) {
            foreach ($validated['actions'] as $action) {
                $npc->actions()->create($action);
            }
        }

        // Create spellcasting
        if ($request->boolean('has_spellcasting') && !empty($validated['spellcasting'])) {
            $npc->spellcasting()->create($validated['spellcasting']);
        }

        return redirect()
            ->route('npcs.show', $npc)
            ->with('success', 'NPC created successfully!');
    }

    /**
     * Display the specified NPC.
     */
    public function show(Npc $npc): View
    {
        $npc->load(['folder', 'traits', 'actions', 'spellcasting']);
        $folders = Folder::treeOptions();

        return view('npcs.show', compact('npc', 'folders'));
    }

    /**
     * Show the form for editing the specified NPC.
     */
    public function edit(Npc $npc): View
    {
        $npc->load(['traits', 'actions', 'spellcasting']);
        $folders = Folder::treeOptions();
        
        return view('npcs.edit', compact('npc', 'folders'));
    }

    /**
     * Update the specified NPC.
     */
    public function update(Request $request, Npc $npc): RedirectResponse|Response
    {
        $validator = $this->makeNpcValidator($request);

        if ($validator->fails()) {
            return $this->renderUpdateValidationFailure($request, $npc, $validator);
        }

        $validated = $this->sanitizeNpcData($validator->validated());

        $npc->update($validated);

        // Sync traits
        $npc->traits()->delete();
        if (!empty($validated['traits'])) {
            foreach ($validated['traits'] as $trait) {
                $npc->traits()->create($trait);
            }
        }

        // Sync actions
        $npc->actions()->delete();
        if (!empty($validated['actions'])) {
            foreach ($validated['actions'] as $action) {
                $npc->actions()->create($action);
            }
        }

        // Sync spellcasting
        $npc->spellcasting()->delete();
        if ($request->boolean('has_spellcasting') && !empty($validated['spellcasting'])) {
            $npc->spellcasting()->create($validated['spellcasting']);
        }

        return redirect()
            ->route('npcs.show', $npc)
            ->with('success', 'NPC updated successfully!');
    }

    /**
     * Remove the specified NPC.
     */
    public function destroy(Npc $npc): RedirectResponse
    {
        $npc->delete();

        return redirect()
            ->route('npcs.index')
            ->with('success', 'NPC deleted successfully!');
    }

    /**
     * Duplicate an NPC.
     */
    public function duplicate(Npc $npc): RedirectResponse
    {
        $clone = $npc->duplicate();

        return redirect()
            ->route('npcs.edit', $clone)
            ->with('success', 'NPC duplicated successfully! You can now edit the copy.');
    }

    /**
     * Generate a random NPC.
     */
    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'challenge_rating' => 'nullable|string',
            'alignment' => 'nullable|string',
            'npc_type' => 'nullable|string',
        ]);

        $npc = app(\App\Services\NpcGenerator::class)->generate($validated);

        return redirect()
            ->route('npcs.edit', $npc)
            ->with('success', 'NPC generated! Feel free to customize it.');
    }

    /**
     * Move an NPC to a different folder.
     */
    public function move(Npc $npc, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        $npc->update($validated);

        $folderName = $npc->folder?->name ?? 'Root';

        return redirect()
            ->back()
            ->with('success', "NPC moved to '{$folderName}'.");
    }

    /**
     * Validate the NPC payload.
     */
    private function makeNpcValidator(Request $request): ValidationValidator
    {
        $validator = Validator::make($request->all(), $this->npcValidationRules());
        $this->validateAttackActions($validator, $request->input('actions', []));

        return $validator;
    }

    /**
     * Render the create form directly on validation failure to avoid flashing the
     * full NPC payload into cookie-backed sessions.
     */
    private function renderCreateValidationFailure(Request $request, ValidationValidator $validator): Response
    {
        return $this->renderValidationFailure('npcs.create', [
            'folders' => Folder::treeOptions(),
            'templates' => Npc::templates()->orderBy('name')->get(),
            'sourceNpc' => null,
            'defaultIsTemplate' => $request->boolean('is_template'),
            'formData' => $this->npcFormData($request),
        ], $validator);
    }

    /**
     * Render the edit form directly on validation failure to avoid oversized
     * redirect/session headers while preserving the submitted data.
     */
    private function renderUpdateValidationFailure(Request $request, Npc $npc, ValidationValidator $validator): Response
    {
        return $this->renderValidationFailure('npcs.edit', [
            'npc' => $npc,
            'folders' => Folder::treeOptions(),
            'formData' => $this->npcFormData($request),
        ], $validator);
    }

    /**
     * Build a validation response without relying on flashed old input.
     */
    private function renderValidationFailure(string $view, array $data, ValidationValidator $validator): Response
    {
        $errors = new ViewErrorBag();
        $errors->put('default', $validator->errors());

        return response()->view($view, $data + ['errors' => $errors], 422);
    }

    /**
     * Keep only form fields that belong to the NPC editor payload.
     */
    private function npcFormData(Request $request): array
    {
        return $request->except(['_token', '_method']);
    }

    /**
     * Get the NPC validation rules.
     */
    private function npcValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'npc_type' => 'nullable|string|max:255',
            'alignment' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'personality_traits' => 'nullable|string',
            'ideals' => 'nullable|string',
            'bonds' => 'nullable|string',
            'flaws' => 'nullable|string',
            'armor_class' => 'nullable|integer|min:0',
            'armor_type' => 'nullable|string|max:255',
            'hit_points' => 'nullable|integer|min:0',
            'hit_dice' => 'nullable|string|max:50',
            'speed' => 'nullable|string|max:255',
            'strength' => 'integer|min:1|max:30',
            'dexterity' => 'integer|min:1|max:30',
            'constitution' => 'integer|min:1|max:30',
            'intelligence' => 'integer|min:1|max:30',
            'wisdom' => 'integer|min:1|max:30',
            'charisma' => 'integer|min:1|max:30',
            'saving_throw_proficiencies' => 'nullable|array',
            'skill_proficiencies' => 'nullable|array',
            'damage_vulnerabilities' => 'nullable|array',
            'damage_resistances' => 'nullable|array',
            'damage_immunities' => 'nullable|array',
            'condition_immunities' => 'nullable|array',
            'senses' => 'nullable|array',
            'languages' => 'nullable|array',
            'challenge_rating' => 'nullable|string|max:10',
            'proficiency_bonus' => 'nullable|integer|min:0',
            'folder_id' => 'nullable|exists:folders,id',
            'is_template' => 'boolean',
            // Related models
            'traits' => 'nullable|array',
            'traits.*.id' => 'nullable|exists:npc_traits,id',
            'traits.*.name' => 'required_with:traits|string|max:255',
            'traits.*.description' => 'required_with:traits|string',
            'actions' => 'nullable|array',
            'actions.*.id' => 'nullable|exists:npc_actions,id',
            'actions.*.name' => 'required_with:actions|string|max:255',
            'actions.*.description' => 'required_with:actions|string',
            'actions.*.action_type' => ['required_with:actions', Rule::in(array_keys(NpcAction::ACTION_TYPES))],
            'actions.*.legendary_cost' => 'nullable|integer|min:1',
            'actions.*.attack_kind' => ['nullable', Rule::in(array_keys(NpcAction::ATTACK_KINDS))],
            'actions.*.attack_range_text' => 'nullable|string|max:255',
            'actions.*.attack_to_hit' => 'nullable|integer|min:-99|max:99',
            'actions.*.attack_target' => 'nullable|string|max:255',
            'actions.*.attack_hit' => 'nullable|string|max:255',
            'actions.*.attack_hit_2' => 'nullable|string|max:255',
            // Spellcasting
            'has_spellcasting' => 'boolean',
            'spellcasting.ability' => 'required_if:has_spellcasting,true|string',
            'spellcasting.spell_save_dc' => 'nullable|integer',
            'spellcasting.spell_attack_bonus' => 'nullable|integer',
            'spellcasting.caster_level' => 'nullable|string',
            'spellcasting.spellcasting_notes' => 'nullable|string',
            'spellcasting.spells' => 'nullable|array',
        ];
    }

    /**
     * Add attack-action-specific validation requirements.
     */
    private function validateAttackActions(ValidationValidator $validator, array $actions): void
    {
        $validator->after(function (ValidationValidator $validator) use ($actions) {
            foreach ($actions as $index => $action) {
                if (($action['action_type'] ?? null) !== NpcAction::TYPE_ATTACK) {
                    continue;
                }

                $requiredFields = [
                    'attack_kind' => 'Attack kind',
                    'attack_range_text' => 'Range or reach',
                    'attack_to_hit' => 'To hit',
                    'attack_target' => 'Target',
                    'attack_hit' => 'On hit',
                ];

                foreach ($requiredFields as $field => $label) {
                    if (blank($action[$field] ?? null)) {
                        $validator->errors()->add("actions.$index.$field", "{$label} is required for attack actions.");
                    }
                }
            }
        });
    }

    /**
     * Normalize note fields based on whether the record is a template.
     */
    private function sanitizeNpcData(array $validated): array
    {
        $validated['actions'] = $this->sanitizeActions($validated['actions'] ?? []);

        if (!($validated['is_template'] ?? false)) {
            return $validated;
        }

        $validated['personality_traits'] = null;
        $validated['ideals'] = null;
        $validated['bonds'] = null;
        $validated['flaws'] = null;

        return $validated;
    }

    /**
     * Normalize action payloads so fields only persist on compatible action types.
     */
    private function sanitizeActions(array $actions): array
    {
        return array_map(function (array $action): array {
            if (($action['action_type'] ?? null) !== NpcAction::TYPE_LEGENDARY_ACTION) {
                $action['legendary_cost'] = null;
            }

            if (($action['action_type'] ?? null) !== NpcAction::TYPE_ATTACK) {
                foreach (NpcAction::ATTACK_FIELDS as $field) {
                    $action[$field] = null;
                }

                return $action;
            }

            $action['legendary_cost'] = null;

            return $action;
        }, $actions);
    }
}
