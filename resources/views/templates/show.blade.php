@extends('layouts.app')

@section('title', 'Template: ' . $template->name)

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('templates.index') }}">Templates</a></li>
            <li class="breadcrumb-item active">{{ $template->name }}</li>
        </ol>
    </nav>

    <!-- Action Buttons -->
    <div class="d-flex justify-content-end gap-2 mb-3">
        <a
            href="{{ route('npcs.create', ['from_template' => $template->id]) }}"
            class="btn btn-danger"
            data-template-hit-point-link
            data-template-hit-point-choice-required="{{ $template->requiresTemplateHitPointChoice() ? 'true' : 'false' }}"
            data-template-name="{{ $template->name }}"
            data-template-hit-points="{{ $template->hit_points ?? '' }}"
            data-template-hit-dice="{{ $template->hit_dice ?? '' }}"
        >
            <i class="bi bi-plus-circle"></i> Create NPC from Template
        </a>
        <a href="{{ route('npcs.edit', $template) }}" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit Template
        </a>
    </div>

    <!-- Template Stat Block (Same as NPC show) -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card npc-card">
                <div class="card-header text-center">
                    <h2 class="mb-0">{{ $template->name }}</h2>
                    <span class="badge bg-secondary">Template</span>
                </div>
                <div class="card-body">
                    <p class="text-center fst-italic mb-3">
                        {{ $template->npc_type ?? 'Unknown Type' }}, {{ $template->alignment ?? 'Unaligned' }}
                    </p>

                    <hr class="dnd-divider">

                    <p><strong>Armor Class</strong> {{ $template->armor_class ?? '10' }}{{ $template->armor_type ? ' (' . $template->armor_type . ')' : '' }}</p>
                    <p><strong>Hit Points</strong> {{ $template->hit_points ?? '1' }}{{ $template->hit_dice ? ' (' . $template->hit_dice . ')' : '' }}</p>
                    <p><strong>Speed</strong> {{ $template->speed ?? '30 ft.' }}</p>

                    <hr class="dnd-divider">

                    <div class="stat-block text-center py-2">
                        <div class="stat">
                            <div class="stat-name">STR</div>
                            <div class="stat-value">{{ $template->strength }} ({{ \App\Models\Npc::formatModifier($template->strength_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">DEX</div>
                            <div class="stat-value">{{ $template->dexterity }} ({{ \App\Models\Npc::formatModifier($template->dexterity_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">CON</div>
                            <div class="stat-value">{{ $template->constitution }} ({{ \App\Models\Npc::formatModifier($template->constitution_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">INT</div>
                            <div class="stat-value">{{ $template->intelligence }} ({{ \App\Models\Npc::formatModifier($template->intelligence_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">WIS</div>
                            <div class="stat-value">{{ $template->wisdom }} ({{ \App\Models\Npc::formatModifier($template->wisdom_modifier) }})</div>
                        </div>
                        <div class="stat">
                            <div class="stat-name">CHA</div>
                            <div class="stat-value">{{ $template->charisma }} ({{ \App\Models\Npc::formatModifier($template->charisma_modifier) }})</div>
                        </div>
                    </div>

                    <hr class="dnd-divider">

                    <p><strong>Challenge</strong> {{ $template->challenge_rating ?? '0' }}</p>

                    @if($template->notes)
                        <hr class="dnd-divider">

                        <h5 class="text-danger mt-4">Notes</h5>
                        <p class="npc-notes-content">{{ $template->notes }}</p>
                    @endif

                    @if($template->traits->count() > 0)
                        <h5 class="text-danger mt-4">Traits</h5>
                        @foreach($template->traits as $trait)
                            <p>
                                <strong><em>{{ $trait->name }}.</em></strong>
                                {{ $trait->description }}
                            </p>
                        @endforeach
                    @endif

                    @php
                        $templateActions = $template->actions->whereIn('action_type', [
                            \App\Models\NpcAction::TYPE_ACTION,
                            \App\Models\NpcAction::TYPE_ATTACK,
                        ]);
                    @endphp

                    @if($templateActions->count() > 0)
                        <h5 class="text-danger mt-4">Actions</h5>
                        @foreach($templateActions as $action)
                            @include('npcs.partials.action', ['action' => $action])
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('templates.partials.hit-point-choice-modal')
@endsection
