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
        $folders = Folder::orderBy('name')->get();

        return view('npcs.index', compact('npcs', 'folders'));
    }

    /**
     * Show the form for creating a new NPC.
     */
    public function create(Request $request): View
    {
        $folders = Folder::orderBy('name')->get();
        $templates = Npc::templates()->orderBy('name')->get();
        $sourceNpc = null;

        // If creating from template or existing NPC
        if ($request->filled('from_template')) {
            $sourceNpc = Npc::with(['traits', 'actions', 'spellcasting'])
                ->find($request->from_template);
        } elseif ($request->filled('from_npc')) {
            $sourceNpc = Npc::with(['traits', 'actions', 'spellcasting'])
                ->find($request->from_npc);
        }

        return view('npcs.create', compact('folders', 'templates', 'sourceNpc'));
    }

    /**
     * Store a newly created NPC.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'npc_type' => 'nullable|string|max:255',
            'alignment' => 'nullable|string|max:255',
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
            'traits.*.name' => 'required_with:traits|string|max:255',
            'traits.*.description' => 'required_with:traits|string',
            'actions' => 'nullable|array',
            'actions.*.name' => 'required_with:actions|string|max:255',
            'actions.*.description' => 'required_with:actions|string',
            'actions.*.action_type' => 'required_with:actions|in:action,bonus_action,reaction,legendary_action',
            'actions.*.legendary_cost' => 'nullable|integer|min:1',
            // Spellcasting
            'has_spellcasting' => 'boolean',
            'spellcasting.ability' => 'required_if:has_spellcasting,true|string',
            'spellcasting.spell_save_dc' => 'nullable|integer',
            'spellcasting.spell_attack_bonus' => 'nullable|integer',
            'spellcasting.caster_level' => 'nullable|string',
            'spellcasting.spellcasting_notes' => 'nullable|string',
            'spellcasting.spells' => 'nullable|array',
        ]);

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
        
        return view('npcs.show', compact('npc'));
    }

    /**
     * Show the form for editing the specified NPC.
     */
    public function edit(Npc $npc): View
    {
        $npc->load(['traits', 'actions', 'spellcasting']);
        $folders = Folder::orderBy('name')->get();
        
        return view('npcs.edit', compact('npc', 'folders'));
    }

    /**
     * Update the specified NPC.
     */
    public function update(Request $request, Npc $npc): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'npc_type' => 'nullable|string|max:255',
            'alignment' => 'nullable|string|max:255',
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
            'actions.*.action_type' => 'required_with:actions|in:action,bonus_action,reaction,legendary_action',
            'actions.*.legendary_cost' => 'nullable|integer|min:1',
            // Spellcasting
            'has_spellcasting' => 'boolean',
            'spellcasting.ability' => 'required_if:has_spellcasting,true|string',
            'spellcasting.spell_save_dc' => 'nullable|integer',
            'spellcasting.spell_attack_bonus' => 'nullable|integer',
            'spellcasting.caster_level' => 'nullable|string',
            'spellcasting.spellcasting_notes' => 'nullable|string',
            'spellcasting.spells' => 'nullable|array',
        ]);

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
}
