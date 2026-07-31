@extends('layouts.app')

@section('title', $npc->name)

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('npcs.index') }}">NPCs</a></li>
            @if($npc->folder)
                <li class="breadcrumb-item"><a href="{{ route('folders.show', $npc->folder) }}">{{ $npc->folder->name }}</a></li>
            @endif
            <li class="breadcrumb-item active">{{ $npc->name }}</li>
        </ol>
    </nav>

    <!-- Action Buttons -->
    <div class="d-flex justify-content-end gap-2 mb-3">
        <a href="{{ route('npcs.edit', $npc) }}" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#moveModal">
            <i class="bi bi-arrow-left-right"></i> Move
        </button>
        <form action="{{ route('npcs.duplicate', $npc) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-secondary">
                <i class="bi bi-copy"></i> Duplicate
            </button>
        </form>
        <form action="{{ route('npcs.destroy', $npc) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this NPC?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash"></i> Delete
            </button>
        </form>
    </div>

    <!-- NPC Stat Block -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card npc-card">
                <div class="card-header text-center">
                    <h2 class="mb-0">{{ $npc->name }}</h2>
                </div>
                <div class="card-body">
                    <p class="text-center fst-italic mb-3">
                        {{ $npc->npc_type ?? 'Unknown Type' }}, {{ $npc->alignment ?? 'Unaligned' }}
                    </p>

                    <hr class="dnd-divider">

                    <!-- Basic Stats -->
                    <p><strong>Armor Class</strong> {{ $npc->armor_class ?? '10' }}{{ $npc->armor_type ? ' (' . $npc->armor_type . ')' : '' }}</p>
                    <p><strong>Hit Points</strong> {{ $npc->hit_points ?? '1' }}{{ $npc->hit_dice ? ' (' . $npc->hit_dice . ')' : '' }}</p>
                    <p><strong>Speed</strong> {{ $npc->speed ?? '30 ft.' }}</p>

                    <hr class="dnd-divider">

                    <!-- Ability Scores -->
                    <div class="stat-block text-center py-2">
                        <div class="stat">
                            <div class="stat-name">STR</div>
                            <div class="stat-value">{{ $npc->strength }} ({{ \App\Models\Npc::formatModifier($npc->strength_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">DEX</div>
                            <div class="stat-value">{{ $npc->dexterity }} ({{ \App\Models\Npc::formatModifier($npc->dexterity_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">CON</div>
                            <div class="stat-value">{{ $npc->constitution }} ({{ \App\Models\Npc::formatModifier($npc->constitution_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">INT</div>
                            <div class="stat-value">{{ $npc->intelligence }} ({{ \App\Models\Npc::formatModifier($npc->intelligence_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">WIS</div>
                            <div class="stat-value">{{ $npc->wisdom }} ({{ \App\Models\Npc::formatModifier($npc->wisdom_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">CHA</div>
                            <div class="stat-value">{{ $npc->charisma }} ({{ \App\Models\Npc::formatModifier($npc->charisma_modifier) }})</div>
                        </div>
                    </div>

                    <hr class="dnd-divider">

                    <!-- Saving Throws & Skills -->
                    @if($npc->saving_throw_proficiencies && count($npc->saving_throw_proficiencies) > 0)
                        <p><strong>Saving Throws</strong> {{ implode(', ', $npc->saving_throw_proficiencies) }}</p>
                    @endif

                    @if($npc->skill_proficiencies && count($npc->skill_proficiencies) > 0)
                        <p><strong>Skills</strong> {{ implode(', ', $npc->skill_proficiencies) }}</p>
                    @endif

                    <!-- Damage/Condition Modifiers -->
                    @if($npc->damage_vulnerabilities && count($npc->damage_vulnerabilities) > 0)
                        <p><strong>Damage Vulnerabilities</strong> {{ implode(', ', $npc->damage_vulnerabilities) }}</p>
                    @endif

                    @if($npc->damage_resistances && count($npc->damage_resistances) > 0)
                        <p><strong>Damage Resistances</strong> {{ implode(', ', $npc->damage_resistances) }}</p>
                    @endif

                    @if($npc->damage_immunities && count($npc->damage_immunities) > 0)
                        <p><strong>Damage Immunities</strong> {{ implode(', ', $npc->damage_immunities) }}</p>
                    @endif

                    @if($npc->condition_immunities && count($npc->condition_immunities) > 0)
                        <p><strong>Condition Immunities</strong> {{ implode(', ', $npc->condition_immunities) }}</p>
                    @endif

                    <!-- Senses -->
                    @if($npc->senses && count($npc->senses) > 0)
                        <p>
                            <strong>Senses</strong>
                            {{ implode(', ', $npc->formatted_senses) }}, passive Perception {{ $npc->passive_perception }}
                        </p>
                    @else
                        <p><strong>Senses</strong> passive Perception {{ $npc->passive_perception }}</p>
                    @endif

                    <!-- Languages -->
                    <p><strong>Languages</strong> {{ $npc->languages && count($npc->languages) > 0 ? implode(', ', $npc->languages) : '—' }}</p>

                    <!-- Challenge Rating -->
                    <p><strong>Challenge</strong> {{ $npc->challenge_rating ?? '0' }} ({{ $npc->proficiency_bonus ? '+' . $npc->proficiency_bonus : '+2' }} Proficiency Bonus)</p>

                    @if($npc->notes || $npc->hasCharacterNotes())
                        <hr class="dnd-divider">

                        <h5 class="text-danger">Notes</h5>

                        @if($npc->notes)
                            <p class="npc-notes-content">{{ $npc->notes }}</p>
                        @endif

                        @if($npc->hasCharacterNotes())
                            <dl class="row mb-0">
                                @if($npc->personality_traits)
                                    <dt class="col-sm-3">Personality Traits</dt>
                                    <dd class="col-sm-9 npc-notes-content">{{ $npc->personality_traits }}</dd>
                                @endif

                                @if($npc->ideals)
                                    <dt class="col-sm-3">Ideals</dt>
                                    <dd class="col-sm-9 npc-notes-content">{{ $npc->ideals }}</dd>
                                @endif

                                @if($npc->bonds)
                                    <dt class="col-sm-3">Bonds</dt>
                                    <dd class="col-sm-9 npc-notes-content">{{ $npc->bonds }}</dd>
                                @endif

                                @if($npc->flaws)
                                    <dt class="col-sm-3">Flaws</dt>
                                    <dd class="col-sm-9 npc-notes-content">{{ $npc->flaws }}</dd>
                                @endif
                            </dl>
                        @endif
                    @endif

                    <hr class="dnd-divider">

                    <!-- Traits -->
                    @if($npc->traits->count() > 0)
                        <h5 class="text-danger">Traits</h5>
                        @foreach($npc->traits as $trait)
                            <p>
                                <strong><em>{{ $trait->name }}.</em></strong>
                                {{ $trait->description }}
                            </p>
                        @endforeach
                    @endif

                    <!-- Spellcasting -->
                    @if($npc->spellcasting)
                        <h5 class="text-danger mt-4">Spellcasting</h5>
                        <p>
                            <strong><em>Spellcasting.</em></strong>
                            The {{ strtolower($npc->name) }} is a {{ $npc->spellcasting->caster_level ?? '1st' }}-level spellcaster.
                            Its spellcasting ability is {{ $npc->spellcasting->ability }}
                            (spell save DC {{ $npc->spellcasting->spell_save_dc ?? 10 }}, {{ $npc->spellcasting->spell_attack_bonus >= 0 ? '+' : '' }}{{ $npc->spellcasting->spell_attack_bonus ?? 0 }} to hit with spell attacks).
                            @if($npc->spellcasting->spellcasting_notes)
                                {{ $npc->spellcasting->spellcasting_notes }}
                            @endif
                        </p>
                        @if($npc->spellcasting->spells)
                            <ul class="list-unstyled ms-3">
                                @foreach($npc->spellcasting->spells as $level => $spells)
                                    @if(is_array($spells) && count($spells) > 0)
                                        <li>
                                            <strong>{{ $level == 0 ? 'Cantrips (at will)' : $ordinal($level) . ' level' }}:</strong>
                                            <em>{{ implode(', ', $spells) }}</em>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    @endif

                    <!-- Actions -->
                    @php
                        $mainActions = $npc->actions->whereIn('action_type', [
                            \App\Models\NpcAction::TYPE_ACTION,
                            \App\Models\NpcAction::TYPE_ATTACK,
                        ]);
                        $bonusActions = $npc->actions->where('action_type', \App\Models\NpcAction::TYPE_BONUS_ACTION);
                        $reactions = $npc->actions->where('action_type', \App\Models\NpcAction::TYPE_REACTION);
                        $legendaryActions = $npc->actions->where('action_type', \App\Models\NpcAction::TYPE_LEGENDARY_ACTION);
                    @endphp

                    @if($mainActions->count() > 0)
                        <h5 class="text-danger mt-4">Actions</h5>
                        @foreach($mainActions as $action)
                            @include('npcs.partials.action', ['action' => $action])
                        @endforeach
                    @endif

                    @if($bonusActions->count() > 0)
                        <h5 class="text-danger mt-4">Bonus Actions</h5>
                        @foreach($bonusActions as $action)
                            @include('npcs.partials.action', ['action' => $action])
                        @endforeach
                    @endif

                    @if($reactions->count() > 0)
                        <h5 class="text-danger mt-4">Reactions</h5>
                        @foreach($reactions as $action)
                            @include('npcs.partials.action', ['action' => $action])
                        @endforeach
                    @endif

                    @if($legendaryActions->count() > 0)
                        <h5 class="text-danger mt-4">Legendary Actions</h5>
                        <p class="text-muted small">
                            The {{ strtolower($npc->name) }} can take 3 legendary actions, choosing from the options below.
                            Only one legendary action option can be used at a time and only at the end of another creature's turn.
                            The {{ strtolower($npc->name) }} regains spent legendary actions at the start of its turn.
                        </p>
                        @foreach($legendaryActions as $action)
                            @include('npcs.partials.action', ['action' => $action])
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $ordinal = function ($number) {
        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

        if (($number % 100) >= 11 && ($number % 100) <= 13) {
            return $number . 'th';
        }

        return $number . $ends[$number % 10];
    };
@endphp

<!-- Move Modal -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move NPC to Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('npcs.move', $npc) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folder_id" class="form-label">Select Folder</label>
                        <select class="form-select" name="folder_id" id="folder_id">
                            <option value="">Root (No Folder)</option>
                            @php
                                $folders = \App\Models\Folder::orderBy('name')->get();
                            @endphp
                            @foreach($folders as $folder)
                                <option value="{{ $folder->id }}" {{ $npc->folder_id === $folder->id ? 'selected' : '' }}>
                                    {{ $folder->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Move</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
