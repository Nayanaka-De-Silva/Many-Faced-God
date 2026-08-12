<?php

namespace App\Http\Controllers;

use App\Models\Npc;
use App\Models\Folder;
use App\Models\NpcTrait;
use App\Models\NpcAction;
use App\Models\NpcCastingProfile;
use App\Models\NpcInnateSpellEntry;
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
            $sourceNpc = Npc::with(['traits', 'actions', 'spellcasting', 'castingProfiles.innateEntries'])
                ->find($request->from_template);

            $sourceNpc?->applyTemplateHitPointMode($templateHitPointMode);
        } elseif ($request->filled('from_npc')) {
            $sourceNpc = Npc::with(['traits', 'actions', 'spellcasting', 'castingProfiles.innateEntries'])
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

        // Create legacy spellcasting block (kept untouched per product decision)
        if ($request->boolean('has_spellcasting') && !empty($validated['spellcasting'])) {
            $npc->spellcasting()->create($validated['spellcasting']);
        }

        // Create Phase 2 casting profiles (delete-and-recreate pattern; cascade handles entries)
        if (!empty($validated['casting_profiles'])) {
            $this->createCastingProfiles($npc, $validated['casting_profiles']);
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
        $npc->load(Npc::STATBLOCK_EAGER_LOADS);
        $folders = Folder::treeOptions();

        return view('npcs.show', compact('npc', 'folders'));
    }

    /**
     * Show the form for editing the specified NPC.
     */
    public function edit(Npc $npc): View
    {
        $npc->load(['traits', 'actions', 'spellcasting', 'castingProfiles.innateEntries']);
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

        // Sync legacy spellcasting block (kept untouched per product decision)
        $npc->spellcasting()->delete();
        if ($request->boolean('has_spellcasting') && !empty($validated['spellcasting'])) {
            $npc->spellcasting()->create($validated['spellcasting']);
        }

        // Sync casting profiles only when the form actually submitted the section (a hidden
        // marker input, always present, distinguishes "submitted with zero profiles" from "this
        // request doesn't know about the field at all" — plain $request->has('casting_profiles')
        // can't tell those apart, since a zero-row repeater sends no casting_profiles key either).
        if ($request->has('casting_profiles_submitted')) {
            $npc->castingProfiles()->delete(); // FK cascade removes innate entries too
            if (!empty($validated['casting_profiles'])) {
                $this->createCastingProfiles($npc, $validated['casting_profiles']);
            }
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
        $this->validateCastingProfiles($validator, $request->input('casting_profiles', []));

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
            'senses.*.type' => 'required_with:senses|string|max:255',
            'senses.*.category' => ['required_with:senses', Rule::in(array_keys(Npc::SENSE_CATEGORIES))],
            'senses.*.range' => 'nullable|string|max:255',
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
            // Spellcasting (legacy "Description" block — kept untouched)
            'has_spellcasting' => 'boolean',
            'spellcasting.ability' => 'required_if:has_spellcasting,true|string',
            'spellcasting.spell_save_dc' => 'nullable|integer',
            'spellcasting.spell_attack_bonus' => 'nullable|integer',
            'spellcasting.caster_level' => 'nullable|string',
            'spellcasting.spellcasting_notes' => 'nullable|string',
            'spellcasting.spells' => 'nullable|array',
            // Casting profiles (Phase 2)
            'casting_profiles' => 'nullable|array',
            'casting_profiles.*.casting_type' => [
                'required',
                Rule::in([NpcCastingProfile::TYPE_INNATE, NpcCastingProfile::TYPE_SPELLCASTING, NpcCastingProfile::TYPE_PACT_MAGIC]),
            ],
            'casting_profiles.*.spellcasting_ability' => ['required', Rule::in(NpcCastingProfile::ABILITIES)],
            'casting_profiles.*.save_dc' => 'required|integer|min:1|max:40',
            'casting_profiles.*.attack_bonus' => 'nullable|integer|min:-99|max:99',
            'casting_profiles.*.source' => 'required|string|max:255',
            'casting_profiles.*.homebrew' => 'boolean',
            'casting_profiles.*.psionics' => 'boolean',
            'casting_profiles.*.caster_level' => 'nullable|integer|min:1|max:30',
            'casting_profiles.*.source_class' => 'nullable|string|max:255',
            'casting_profiles.*.race_or_origin' => 'nullable|string|max:255',
            'casting_profiles.*.slots' => 'nullable|array',
            'casting_profiles.*.slots.*' => 'nullable|integer|min:0',
            'casting_profiles.*.slot_level' => 'nullable|integer|min:1|max:9',
            'casting_profiles.*.slot_count' => 'nullable|integer|min:0|max:99',
            'casting_profiles.*.cantrips' => 'nullable|array',
            'casting_profiles.*.cantrips.*.library_id' => 'nullable|string',
            'casting_profiles.*.cantrips.*.name' => 'required|string|max:255',
            'casting_profiles.*.spells_known_or_prepared' => 'nullable|array',
            'casting_profiles.*.spells_known_or_prepared.*.library_id' => 'nullable|string',
            'casting_profiles.*.spells_known_or_prepared.*.name' => 'required|string|max:255',
            'casting_profiles.*.innate_entries' => 'nullable|array',
            'casting_profiles.*.innate_entries.*.spell_name' => 'required|string|max:255',
            'casting_profiles.*.innate_entries.*.spell_library_id' => 'nullable|string',
            'casting_profiles.*.innate_entries.*.usage' => [
                'required',
                Rule::in([NpcInnateSpellEntry::USAGE_AT_WILL, NpcInnateSpellEntry::USAGE_PER_DAY]),
            ],
            'casting_profiles.*.innate_entries.*.uses_per_day' => 'nullable|integer|min:1',
            'casting_profiles.*.innate_entries.*.restriction' => 'nullable|string|max:255',
            'casting_profiles.*.innate_entries.*.cast_level' => 'nullable|integer|min:1|max:9',
            'casting_profiles.*.innate_entries.*.sort_order' => 'nullable|integer|min:0',
            'casting_profiles.*.sort_order' => 'nullable|integer|min:0',
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
     * Add casting-profile-specific conditional validation requirements.
     * Mirrors validateAttackActions() — called from makeNpcValidator()'s after-closure.
     *
     * Enforces conditional requiredness the base rules can't express on their own:
     * - Innate entries: usage=PerDay requires uses_per_day; usage=AtWill must NOT have uses_per_day.
     * - Spellcasting: requires caster_level, source_class, attack_bonus.
     * - PactMagic: requires caster_level, slot_level, slot_count.
     */
    private function validateCastingProfiles(ValidationValidator $validator, array $profiles): void
    {
        $validator->after(function (ValidationValidator $validator) use ($profiles) {
            foreach ($profiles as $profileIndex => $profile) {
                $type = $profile['casting_type'] ?? null;

                // Per-type required field checks
                if ($type === NpcCastingProfile::TYPE_SPELLCASTING) {
                    if (blank($profile['caster_level'] ?? null)) {
                        $validator->errors()->add("casting_profiles.{$profileIndex}.caster_level", 'Caster level is required for Spellcasting profiles.');
                    }
                    if (blank($profile['source_class'] ?? null)) {
                        $validator->errors()->add("casting_profiles.{$profileIndex}.source_class", 'Source class is required for Spellcasting profiles.');
                    }
                    if (is_null($profile['attack_bonus'] ?? null)) {
                        $validator->errors()->add("casting_profiles.{$profileIndex}.attack_bonus", 'Attack bonus is required for Spellcasting profiles.');
                    }
                }

                if ($type === NpcCastingProfile::TYPE_PACT_MAGIC) {
                    if (blank($profile['caster_level'] ?? null)) {
                        $validator->errors()->add("casting_profiles.{$profileIndex}.caster_level", 'Caster level is required for Pact Magic profiles.');
                    }
                    if (blank($profile['slot_level'] ?? null)) {
                        $validator->errors()->add("casting_profiles.{$profileIndex}.slot_level", 'Slot level is required for Pact Magic profiles.');
                    }
                    if (is_null($profile['slot_count'] ?? null)) {
                        $validator->errors()->add("casting_profiles.{$profileIndex}.slot_count", 'Slot count is required for Pact Magic profiles.');
                    }
                }

                // Innate entry PerDay/AtWill consistency
                foreach ($profile['innate_entries'] ?? [] as $entryIndex => $entry) {
                    $usage = $entry['usage'] ?? null;
                    $usesPerDay = $entry['uses_per_day'] ?? null;

                    if ($usage === NpcInnateSpellEntry::USAGE_PER_DAY && blank($usesPerDay)) {
                        $validator->errors()->add(
                            "casting_profiles.{$profileIndex}.innate_entries.{$entryIndex}.uses_per_day",
                            'Uses per day is required when usage is PerDay.'
                        );
                    }

                    if ($usage === NpcInnateSpellEntry::USAGE_AT_WILL && !is_null($usesPerDay)) {
                        $validator->errors()->add(
                            "casting_profiles.{$profileIndex}.innate_entries.{$entryIndex}.uses_per_day",
                            'Uses per day must not be set when usage is AtWill.'
                        );
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
        $validated['casting_profiles'] = $this->sanitizeCastingProfiles($validated['casting_profiles'] ?? []);

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
     * Null out columns that don't belong to the given casting_type.
     * Uses NpcCastingProfile::FIELDS_BY_TYPE to determine which columns to clear.
     * innate_entries is not a column and is left untouched here; the store/update
     * logic extracts it before calling castingProfiles()->create().
     */
    private function sanitizeCastingProfiles(array $profiles): array
    {
        // psionics/homebrew are NOT NULL boolean columns (default false) — clearing them
        // means "false", not "null", or the insert violates the column constraint.
        $notNullableBooleans = ['psionics', 'homebrew'];

        return array_map(function (array $profile) use ($notNullableBooleans): array {
            $type = $profile['casting_type'] ?? null;
            $fieldsToClear = NpcCastingProfile::FIELDS_BY_TYPE[$type] ?? [];

            foreach ($fieldsToClear as $field) {
                $profile[$field] = in_array($field, $notNullableBooleans, true) ? false : null;
            }

            return $profile;
        }, $profiles);
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

    /**
     * Persist casting profiles and their nested innate entries for the given NPC.
     * innate_entries is extracted and removed before mass-assigning the profile row,
     * then re-attached as NpcInnateSpellEntry rows (Innate profiles only).
     */
    private function createCastingProfiles(Npc $npc, array $profiles): void
    {
        // sort_order is derived from submission position rather than trusted from input:
        // the form (a later PR) only ever conveys order via array position, never an
        // explicit field, so deriving it here is correct regardless of what a client sends.
        foreach (array_values($profiles) as $profileIndex => $profileData) {
            $entries = $profileData['innate_entries'] ?? [];
            unset($profileData['innate_entries']); // not a column; remove before create()
            $profileData['sort_order'] = $profileIndex;

            $isInnate = ($profileData['casting_type'] ?? null) === NpcCastingProfile::TYPE_INNATE;

            $profile = $npc->castingProfiles()->create($profileData);

            if ($isInnate && ! empty($entries)) {
                foreach (array_values($entries) as $entryIndex => $entryData) {
                    $entryData['sort_order'] = $entryIndex;
                    $profile->innateEntries()->create($entryData);
                }
            }
        }
    }
}
