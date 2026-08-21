@php
    $npc = $npc ?? null;
    $defaultIsTemplate = $defaultIsTemplate ?? null;
    $formData = $formData ?? null;
    $formValue = function (string $key, $default = null) use ($formData) {
        return $formData !== null
            ? data_get($formData, $key, $default)
            : old($key, $default);
    };
    $isTemplate = $formValue('is_template', $defaultIsTemplate ?? $npc?->is_template);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Basic Info -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-info-circle"></i> Basic Information
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Name *</label>
                <input type="text" name="name" id="name" class="form-control" required
                    value="{{ $formValue('name', $npc?->name) }}">
            </div>
            <div class="col-md-6">
                <label for="npc_type" class="form-label">NPC Type</label>
                <input type="text" name="npc_type" id="npc_type" class="form-control" 
                    placeholder="e.g., Medium Humanoid"
                    value="{{ $formValue('npc_type', $npc?->npc_type) }}">
            </div>
            <div class="col-md-6">
                <label for="alignment" class="form-label">Alignment</label>
                <select name="alignment" id="alignment" class="form-select">
                    <option value="">-- Select --</option>
                    @foreach(\App\Models\Npc::ALIGNMENTS as $alignment)
                        <option value="{{ $alignment }}" {{ $formValue('alignment', $npc?->alignment) == $alignment ? 'selected' : '' }}>
                            {{ $alignment }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label for="folder_id" class="form-label">Folder</label>
                <select name="folder_id" id="folder_id" class="form-select">
                    <option value="">-- No Folder --</option>
                    @foreach($folders as $id => $label)
                        <option value="{{ $id }}" {{ $formValue('folder_id', $npc?->folder_id) == $id ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input type="hidden" name="is_template" value="0">
                    <input type="checkbox" name="is_template" id="is_template" class="form-check-input" value="1"
                        {{ $isTemplate ? 'checked' : '' }}>
                    <label for="is_template" class="form-check-label">Save as Template</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notes -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-journal-text"></i> Notes
    </div>
    <div class="card-body">
        <div id="noteCardsContainer">
            @php $noteCards = $formValue('note_cards', $npc?->noteCards?->toArray() ?? []); @endphp
            @forelse($noteCards as $index => $note)
                <div class="note-card-row border rounded p-3 mb-3" draggable="true">
                    <div class="row g-2 align-items-start">
                        <div class="col-auto d-flex align-items-center">
                            <span class="note-card-handle bi bi-grip-vertical text-muted fs-5"></span>
                        </div>
                        <div class="col">
                            <input type="text" name="note_cards[{{ $index }}][title]"
                                   class="form-control mb-2" placeholder="Note Title"
                                   value="{{ $note['title'] ?? '' }}">
                            <textarea name="note_cards[{{ $index }}][description]"
                                      class="form-control" rows="3"
                                      placeholder="Description (optional)">{{ $note['description'] ?? '' }}</textarea>
                        </div>
                        <div class="col-auto d-flex flex-column gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm note-card-move-up" title="Move up">▲</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm note-card-move-down" title="Move down">▼</button>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-note-card">×</button>
                        </div>
                    </div>
                </div>
            @empty
            @endforelse
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addNoteCard">+ Add Note Card</button>

        <div id="characterNotesSection" style="{{ $isTemplate ? 'display:none;' : '' }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="personality_traits" class="form-label">Personality Traits</label>
                    <textarea name="personality_traits" id="personality_traits" class="form-control" rows="3"
                        placeholder="Distinctive mannerisms, quirks, or habits.">{{ $formValue('personality_traits', $npc?->personality_traits) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="ideals" class="form-label">Ideals</label>
                    <textarea name="ideals" id="ideals" class="form-control" rows="3"
                        placeholder="Core beliefs or principles.">{{ $formValue('ideals', $npc?->ideals) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="bonds" class="form-label">Bonds</label>
                    <textarea name="bonds" id="bonds" class="form-control" rows="3"
                        placeholder="People, places, or causes this NPC cares about.">{{ $formValue('bonds', $npc?->bonds) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="flaws" class="form-label">Flaws</label>
                    <textarea name="flaws" id="flaws" class="form-control" rows="3"
                        placeholder="Weaknesses, vices, or blind spots.">{{ $formValue('flaws', $npc?->flaws) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Combat Stats -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-shield"></i> Combat Statistics
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="armor_class" class="form-label">Armor Class</label>
                <input type="number" name="armor_class" id="armor_class" class="form-control" min="0"
                    value="{{ $formValue('armor_class', $npc?->armor_class ?? 10) }}">
            </div>
            <div class="col-md-3">
                <label for="armor_type" class="form-label">Armor Type</label>
                <input type="text" name="armor_type" id="armor_type" class="form-control" 
                    placeholder="e.g., Chain Mail"
                    value="{{ $formValue('armor_type', $npc?->armor_type) }}">
            </div>
            <div class="col-md-3">
                <label for="hit_points" class="form-label">Hit Points</label>
                <input type="number" name="hit_points" id="hit_points" class="form-control" min="0"
                    value="{{ $formValue('hit_points', $npc?->hit_points) }}">
            </div>
            <div class="col-md-3">
                <label for="hit_dice" class="form-label">Hit Dice</label>
                <input type="text" name="hit_dice" id="hit_dice" class="form-control" 
                    placeholder="e.g., 4d8+8"
                    value="{{ $formValue('hit_dice', $npc?->hit_dice) }}">
            </div>
            <div class="col-md-4">
                <label for="speed" class="form-label">Speed</label>
                <input type="text" name="speed" id="speed" class="form-control" 
                    placeholder="e.g., 30 ft., fly 60 ft."
                    value="{{ $formValue('speed', $npc?->speed ?? '30 ft.') }}">
            </div>
            <div class="col-md-4">
                <label for="challenge_rating" class="form-label">Challenge Rating</label>
                <select name="challenge_rating" id="challenge_rating" class="form-select">
                    <option value="">-- Select --</option>
                    @foreach(['0', '1/8', '1/4', '1/2', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20'] as $cr)
                        <option value="{{ $cr }}" {{ $formValue('challenge_rating', $npc?->challenge_rating) == $cr ? 'selected' : '' }}>
                            {{ $cr }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="proficiency_bonus" class="form-label">Proficiency Bonus</label>
                <input type="number" name="proficiency_bonus" id="proficiency_bonus" class="form-control" min="0"
                    value="{{ $formValue('proficiency_bonus', $npc?->proficiency_bonus ?? 2) }}">
            </div>
        </div>
    </div>
</div>

<!-- Ability Scores -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-person-badge"></i> Ability Scores
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach(['strength' => 'STR', 'dexterity' => 'DEX', 'constitution' => 'CON', 'intelligence' => 'INT', 'wisdom' => 'WIS', 'charisma' => 'CHA'] as $attr => $label)
                <div class="col-md-2">
                    <label for="{{ $attr }}" class="form-label text-center d-block">{{ $label }}</label>
                    <input type="number" name="{{ $attr }}" id="{{ $attr }}" class="form-control text-center" 
                        min="1" max="30" value="{{ $formValue($attr, $npc?->$attr ?? 10) }}">
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Proficiencies -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-star"></i> Proficiencies
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Saving Throw Proficiencies</h6>
                @php $savingThrows = $formValue('saving_throw_proficiencies', $npc?->saving_throw_proficiencies ?? []); @endphp
                @foreach(['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'] as $save)
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="saving_throw_proficiencies[]" 
                            id="save_{{ strtolower($save) }}" 
                            value="{{ $save }}" 
                            class="form-check-input"
                            {{ in_array($save, $savingThrows) ? 'checked' : '' }}>
                        <label class="form-check-label" for="save_{{ strtolower($save) }}">{{ substr($save, 0, 3) }}</label>
                    </div>
                @endforeach
            </div>
            <div class="col-md-6">
                <h6>Skill Proficiencies</h6>
                @php $skills = $formValue('skill_proficiencies', $npc?->skill_proficiencies ?? []); @endphp
                <div class="row">
                    @foreach(array_keys(\App\Models\Npc::SKILLS) as $skill)
                        <div class="col-6">
                            <div class="form-check">
                                <input type="checkbox" name="skill_proficiencies[]" 
                                    id="skill_{{ \Illuminate\Support\Str::slug($skill) }}" 
                                    value="{{ $skill }}" 
                                    class="form-check-input"
                                    {{ in_array($skill, $skills) ? 'checked' : '' }}>
                                <label class="form-check-label" for="skill_{{ \Illuminate\Support\Str::slug($skill) }}">{{ $skill }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Damage & Condition Modifiers -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-shield-exclamation"></i> Damage & Condition Modifiers
    </div>
    <div class="card-body">
        <div class="row">
            @foreach(['damage_vulnerabilities' => 'Vulnerabilities', 'damage_resistances' => 'Resistances', 'damage_immunities' => 'Immunities'] as $field => $label)
                <div class="col-md-4 mb-3">
                    <h6>Damage {{ $label }}</h6>
                    @php $selected = $formValue($field, $npc?->$field ?? []); @endphp
                    <select name="{{ $field }}[]" class="form-select" multiple size="6">
                        @foreach(\App\Models\Npc::DAMAGE_TYPES as $type)
                            <option value="{{ $type }}" {{ in_array($type, $selected) ? 'selected' : '' }}>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                </div>
            @endforeach
        </div>
        <div class="row">
            <div class="col-md-12">
                <h6>Condition Immunities</h6>
                @php $conditions = $formValue('condition_immunities', $npc?->condition_immunities ?? []); @endphp
                <div class="row">
                    @foreach(\App\Models\Npc::CONDITIONS as $condition)
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" name="condition_immunities[]" 
                                    id="cond_{{ \Illuminate\Support\Str::slug($condition) }}" 
                                    value="{{ $condition }}" 
                                    class="form-check-input"
                                    {{ in_array($condition, $conditions) ? 'checked' : '' }}>
                                <label class="form-check-label" for="cond_{{ \Illuminate\Support\Str::slug($condition) }}">{{ $condition }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Senses & Languages -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-eye"></i> Senses & Languages
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Senses</h6>
                <div id="sensesContainer">
                    @php $senses = $formValue('senses', $npc?->senses ?? []); @endphp
                    @forelse($senses as $index => $sense)
                        <div class="input-group mb-2 sense-row">
                            <input type="text" name="senses[{{ $index }}][type]" class="form-control"
                                placeholder="Type (e.g., Darkvision)" value="{{ $sense['type'] ?? '' }}">
                            <select name="senses[{{ $index }}][category]" class="form-select" style="max-width: 80px;">
                                @foreach(\App\Models\Npc::SENSE_CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" {{ ($sense['category'] ?? 'ft') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="senses[{{ $index }}][range]" class="form-control"
                                placeholder="Value" value="{{ $sense['range'] ?? '' }}" style="max-width: 100px;">
                            <button type="button" class="btn btn-outline-danger remove-sense">×</button>
                        </div>
                    @empty
                        <div class="input-group mb-2 sense-row">
                            <input type="text" name="senses[0][type]" class="form-control" placeholder="Type (e.g., Darkvision)">
                            <select name="senses[0][category]" class="form-select" style="max-width: 80px;">
                                @foreach(\App\Models\Npc::SENSE_CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" {{ $key === 'ft' ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="senses[0][range]" class="form-control" placeholder="Value" style="max-width: 100px;">
                            <button type="button" class="btn btn-outline-danger remove-sense">×</button>
                        </div>
                    @endforelse
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="addSense">+ Add Sense</button>
            </div>
            <div class="col-md-6">
                <h6>Languages</h6>
                @php
                    $languages = $formValue('languages', $npc?->languages ?? []);

                    if (! is_array($languages)) {
                        $languages = filled($languages) ? [$languages] : [];
                    }

                    $languagesText = $formValue('languages_text', implode(', ', $languages));
                @endphp
                <input type="text" name="languages_text" id="languages_text" class="form-control" 
                    placeholder="Common, Elvish, Dwarvish"
                    value="{{ $languagesText }}">
                <small class="text-muted">Separate languages with commas</small>
            </div>
        </div>
    </div>
</div>

<!-- Traits -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-bookmark-star"></i> Traits
    </div>
    <div class="card-body">
        <div id="traitsContainer">
            @php $traits = $formValue('traits', $npc?->traits?->toArray() ?? []); @endphp
            @forelse($traits as $index => $trait)
                <div class="trait-row border rounded p-3 mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="traits[{{ $index }}][name]" class="form-control" 
                                placeholder="Trait Name" value="{{ $trait['name'] ?? '' }}">
                        </div>
                        <div class="col-md-7">
                            <textarea name="traits[{{ $index }}][description]" class="form-control" rows="2" 
                                placeholder="Trait Description">{{ $trait['description'] ?? '' }}</textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger w-100 remove-trait">×</button>
                        </div>
                    </div>
                </div>
            @empty
            @endforelse
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="addTrait">+ Add Trait</button>
    </div>
</div>

<!-- Actions -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-lightning"></i> Actions
    </div>
    <div class="card-body">
        <div id="actionsContainer">
            @php $actions = $formValue('actions', $npc?->actions?->toArray() ?? []); @endphp
            @forelse($actions as $index => $action)
                <div class="action-row border rounded p-3 mb-3">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <input type="text" name="actions[{{ $index }}][name]" class="form-control" 
                                placeholder="Action Name" value="{{ $action['name'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <select name="actions[{{ $index }}][action_type]" class="form-select action-type-select">
                                @foreach(\App\Models\NpcAction::ACTION_TYPES as $key => $label)
                                    <option value="{{ $key }}" {{ ($action['action_type'] ?? 'action') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 legendary-cost-col" style="{{ ($action['action_type'] ?? '') == \App\Models\NpcAction::TYPE_LEGENDARY_ACTION ? '' : 'display:none;' }}">
                            <input type="number" name="actions[{{ $index }}][legendary_cost]" class="form-control" 
                                placeholder="Cost" min="1" value="{{ $action['legendary_cost'] ?? 1 }}">
                        </div>
                        <div class="col-md-5">
                            <textarea name="actions[{{ $index }}][description]" class="form-control" rows="2" 
                                placeholder="Action Description">{{ $action['description'] ?? '' }}</textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger w-100 remove-action">×</button>
                        </div>
                        <div class="col-12 attack-fields-row" style="{{ ($action['action_type'] ?? '') == \App\Models\NpcAction::TYPE_ATTACK ? '' : 'display:none;' }}">
                            <div class="row g-2 mt-1">
                                <div class="col-md-2">
                                    <select name="actions[{{ $index }}][attack_kind]" class="form-select">
                                        <option value="">Attack Type</option>
                                        @foreach(\App\Models\NpcAction::ATTACK_KINDS as $key => $label)
                                            <option value="{{ $key }}" {{ ($action['attack_kind'] ?? '') === $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[{{ $index }}][attack_range_text]" class="form-control"
                                        placeholder="Reach / Range" value="{{ $action['attack_range_text'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="actions[{{ $index }}][attack_to_hit]" class="form-control"
                                        placeholder="To Hit" value="{{ $action['attack_to_hit'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[{{ $index }}][attack_target]" class="form-control"
                                        placeholder="Target" value="{{ $action['attack_target'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[{{ $index }}][attack_hit]" class="form-control"
                                        placeholder="On Hit" value="{{ $action['attack_hit'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[{{ $index }}][attack_hit_2]" class="form-control"
                                        placeholder="On Hit 2" value="{{ $action['attack_hit_2'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
            @endforelse
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="addAction">+ Add Action</button>
    </div>
</div>

<!-- Spellcasting (Description) — legacy free-text block; use Casting Profiles below for structured statblock output -->
<div class="card mb-4">
    <div class="card-header bg-dark text-warning">
        <i class="bi bi-magic"></i> Spellcasting (Description)
        <small class="text-muted ms-2 fw-normal">Legacy free-text notes — use Casting Profiles below for structured output</small>
    </div>
    <div class="card-body">
        @php $spellcasting = $formValue('spellcasting', $npc?->spellcasting?->toArray() ?? []); @endphp
        <div class="form-check mb-3">
            <input type="hidden" name="has_spellcasting" value="0">
            <input type="checkbox" name="has_spellcasting" id="has_spellcasting" class="form-check-input" value="1"
                {{ !empty($spellcasting) || $formValue('has_spellcasting') ? 'checked' : '' }}>
            <label for="has_spellcasting" class="form-check-label">This NPC has spellcasting abilities</label>
        </div>
        
        <div id="spellcastingSection" style="{{ !empty($spellcasting) || $formValue('has_spellcasting') ? '' : 'display:none;' }}">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Spellcasting Ability</label>
                    <select name="spellcasting[ability]" class="form-select">
                        @foreach(\App\Models\NpcSpellcasting::SPELLCASTING_ABILITIES as $ability)
                            <option value="{{ $ability }}" {{ ($spellcasting['ability'] ?? '') == $ability ? 'selected' : '' }}>
                                {{ $ability }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Spell Save DC</label>
                    <input type="number" name="spellcasting[spell_save_dc]" class="form-control" 
                        value="{{ $spellcasting['spell_save_dc'] ?? 10 }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Spell Attack Bonus</label>
                    <input type="number" name="spellcasting[spell_attack_bonus]" class="form-control" 
                        value="{{ $spellcasting['spell_attack_bonus'] ?? 0 }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Caster Level</label>
                    <input type="text" name="spellcasting[caster_level]" class="form-control" 
                        placeholder="e.g., 5th" value="{{ $spellcasting['caster_level'] ?? '' }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Spellcasting Notes</label>
                    <textarea name="spellcasting[spellcasting_notes]" class="form-control" rows="2"
                        placeholder="The creature knows the following spells...">{{ $spellcasting['spellcasting_notes'] ?? '' }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

@include('npcs.partials._casting_profiles')

@push('scripts')
<script>
    // Seeded from the highest existing key (not count()) because a validation-failure
    // re-render can leave a gapped note_cards array (e.g. {0, 2} after removing index 1),
    // and count() would collide with a still-present key.
    let noteCardIndex = {{ empty($noteCards ?? []) ? 0 : max(array_keys($noteCards)) + 1 }};
    let traitIndex = {{ count($traits) }};
    let actionIndex = {{ count($actions) }};
    let senseIndex = {{ count($senses ?: [1]) }};

    function toggleCharacterNotesSection() {
        const isTemplateCheckbox = document.getElementById('is_template');
        const characterNotesSection = document.getElementById('characterNotesSection');

        characterNotesSection.style.display = isTemplateCheckbox.checked ? 'none' : '';
    }

    // Note card: add
    document.getElementById('addNoteCard').addEventListener('click', function() {
        const container = document.getElementById('noteCardsContainer');
        const html = `
            <div class="note-card-row border rounded p-3 mb-3" draggable="true">
                <div class="row g-2 align-items-start">
                    <div class="col-auto d-flex align-items-center">
                        <span class="note-card-handle bi bi-grip-vertical text-muted fs-5"></span>
                    </div>
                    <div class="col">
                        <input type="text" name="note_cards[${noteCardIndex}][title]"
                               class="form-control mb-2" placeholder="Note Title">
                        <textarea name="note_cards[${noteCardIndex}][description]"
                                  class="form-control" rows="3"
                                  placeholder="Description (optional)"></textarea>
                    </div>
                    <div class="col-auto d-flex flex-column gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm note-card-move-up" title="Move up">▲</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm note-card-move-down" title="Move down">▼</button>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-note-card">×</button>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        noteCardIndex++;
        updateNoteCardButtons();
    });

    // Note card: remove, move up/down (delegated — rows created dynamically)
    document.addEventListener('click', function(e) {
        const container = document.getElementById('noteCardsContainer');

        if (e.target.classList.contains('remove-note-card')) {
            e.target.closest('.note-card-row').remove();
            updateNoteCardButtons();
            return;
        }

        if (e.target.classList.contains('note-card-move-up')) {
            const row = e.target.closest('.note-card-row');
            const prev = row.previousElementSibling;
            if (prev && prev.classList.contains('note-card-row')) {
                container.insertBefore(row, prev);
                updateNoteCardButtons();
            }
            return;
        }

        if (e.target.classList.contains('note-card-move-down')) {
            const row = e.target.closest('.note-card-row');
            const next = row.nextElementSibling;
            if (next && next.classList.contains('note-card-row')) {
                container.insertBefore(next, row);
                updateNoteCardButtons();
            }
            return;
        }
    });

    function updateNoteCardButtons() {
        const rows = document.querySelectorAll('#noteCardsContainer .note-card-row');
        rows.forEach((row, i) => {
            row.querySelector('.note-card-move-up').disabled = (i === 0);
            row.querySelector('.note-card-move-down').disabled = (i === rows.length - 1);
        });
    }
    updateNoteCardButtons();

    // Note card: HTML5 drag-and-drop reorder
    (function() {
        const container = document.getElementById('noteCardsContainer');
        let dragging = null;

        container.addEventListener('dragstart', function(e) {
            dragging = e.target.closest('.note-card-row');
            if (dragging) {
                dragging.classList.add('dragging');
                // Firefox requires dataTransfer to carry data before it will start the drag.
                e.dataTransfer.setData('text/plain', '');
            }
        });

        container.addEventListener('dragend', function() {
            if (dragging) dragging.classList.remove('dragging');
            dragging = null;
            updateNoteCardButtons();
        });

        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (!dragging) return;
            const target = e.target.closest('.note-card-row');
            if (!target || target === dragging) return;
            const rect = target.getBoundingClientRect();
            const midpoint = rect.top + rect.height / 2;
            if (e.clientY < midpoint) {
                container.insertBefore(dragging, target);
            } else {
                container.insertBefore(dragging, target.nextSibling);
            }
        });
    })();

    // Add Trait
    document.getElementById('addTrait').addEventListener('click', function() {
        const container = document.getElementById('traitsContainer');
        const html = `
            <div class="trait-row border rounded p-3 mb-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="traits[${traitIndex}][name]" class="form-control" placeholder="Trait Name">
                    </div>
                    <div class="col-md-7">
                        <textarea name="traits[${traitIndex}][description]" class="form-control" rows="2" placeholder="Trait Description"></textarea>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100 remove-trait">×</button>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        traitIndex++;
    });

    // Add Action
    document.getElementById('addAction').addEventListener('click', function() {
        const container = document.getElementById('actionsContainer');
        const html = `
            <div class="action-row border rounded p-3 mb-3">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <input type="text" name="actions[${actionIndex}][name]" class="form-control" placeholder="Action Name">
                        </div>
                        <div class="col-md-2">
                            <select name="actions[${actionIndex}][action_type]" class="form-select action-type-select">
                                <option value="action">Action</option>
                                <option value="attack_action">Attack Action</option>
                                <option value="bonus_action">Bonus Action</option>
                                <option value="reaction">Reaction</option>
                                <option value="legendary_action">Legendary Action</option>
                            </select>
                        </div>
                    <div class="col-md-1 legendary-cost-col" style="display:none;">
                        <input type="number" name="actions[${actionIndex}][legendary_cost]" class="form-control" placeholder="Cost" min="1" value="1">
                    </div>
                    <div class="col-md-5">
                        <textarea name="actions[${actionIndex}][description]" class="form-control" rows="2" placeholder="Action Description"></textarea>
                    </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger w-100 remove-action">×</button>
                        </div>
                        <div class="col-12 attack-fields-row" style="display:none;">
                            <div class="row g-2 mt-1">
                                <div class="col-md-2">
                                    <select name="actions[${actionIndex}][attack_kind]" class="form-select">
                                        <option value="">Attack Type</option>
                                        <option value="melee">Melee</option>
                                        <option value="ranged">Ranged</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[${actionIndex}][attack_range_text]" class="form-control" placeholder="Reach / Range">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="actions[${actionIndex}][attack_to_hit]" class="form-control" placeholder="To Hit">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[${actionIndex}][attack_target]" class="form-control" placeholder="Target">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[${actionIndex}][attack_hit]" class="form-control" placeholder="On Hit">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="actions[${actionIndex}][attack_hit_2]" class="form-control" placeholder="On Hit 2">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        toggleActionRowFields(container.lastElementChild);
        actionIndex++;
    });

    // Add Sense
    document.getElementById('addSense').addEventListener('click', function() {
        const container = document.getElementById('sensesContainer');
        const html = `
            <div class="input-group mb-2 sense-row">
                <input type="text" name="senses[${senseIndex}][type]" class="form-control" placeholder="Type (e.g., Darkvision)">
                <select name="senses[${senseIndex}][category]" class="form-select" style="max-width: 80px;">
                    <option value="ft" selected>ft.</option>
                    <option value="dc">DC</option>
                    <option value="other">Other</option>
                </select>
                <input type="text" name="senses[${senseIndex}][range]" class="form-control" placeholder="Value" style="max-width: 100px;">
                <button type="button" class="btn btn-outline-danger remove-sense">×</button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        senseIndex++;
    });

    // Remove handlers
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-trait')) {
            e.target.closest('.trait-row').remove();
        }
        if (e.target.classList.contains('remove-action')) {
            e.target.closest('.action-row').remove();
        }
        if (e.target.classList.contains('remove-sense')) {
            e.target.closest('.sense-row').remove();
        }
    });

    function toggleActionRowFields(actionRow) {
        const actionTypeSelect = actionRow.querySelector('.action-type-select');
        const costCol = actionRow.querySelector('.legendary-cost-col');
        const attackFieldsRow = actionRow.querySelector('.attack-fields-row');

        costCol.style.display = actionTypeSelect.value === 'legendary_action' ? '' : 'none';
        attackFieldsRow.style.display = actionTypeSelect.value === 'attack_action' ? '' : 'none';
    }

    document.querySelectorAll('.action-row').forEach(toggleActionRowFields);

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('action-type-select')) {
            toggleActionRowFields(e.target.closest('.action-row'));
        }
    });

    // Toggle spellcasting section
    document.getElementById('has_spellcasting').addEventListener('change', function() {
        document.getElementById('spellcastingSection').style.display = this.checked ? '' : 'none';
    });

    document.getElementById('is_template').addEventListener('change', toggleCharacterNotesSection);
    toggleCharacterNotesSection();

    // Process languages before submit
    document.getElementById('npcForm').addEventListener('submit', function() {
        const languagesText = document.getElementById('languages_text').value;
        const languages = languagesText.split(',').map(l => l.trim()).filter(l => l);
        
        // Remove existing hidden inputs for languages
        document.querySelectorAll('input[name="languages[]"]').forEach(el => el.remove());
        
        // Add new hidden inputs
        languages.forEach(lang => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'languages[]';
            input.value = lang;
            this.appendChild(input);
        });
    });
</script>
@endpush
