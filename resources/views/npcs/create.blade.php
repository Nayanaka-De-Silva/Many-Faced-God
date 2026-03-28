@extends('layouts.app')

@section('title', 'Create NPC')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-plus-circle"></i> Create NPC</h1>

    <!-- Creation Options -->
    @if(!request('from_template') && !request('from_npc'))
        <div class="card mb-4">
            <div class="card-header bg-dark text-warning">
                <i class="bi bi-lightning"></i> Quick Start Options
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <form action="{{ route('npcs.generate') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100 py-3">
                                <i class="bi bi-dice-5 d-block mb-2" style="font-size: 2rem;"></i>
                                Generate Random NPC
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-secondary w-100 py-3" data-bs-toggle="modal" data-bs-target="#templateModal">
                            <i class="bi bi-file-earmark-text d-block mb-2" style="font-size: 2rem;"></i>
                            Start from Template
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-success w-100 py-3" data-bs-toggle="modal" data-bs-target="#existingModal">
                            <i class="bi bi-copy d-block mb-2" style="font-size: 2rem;"></i>
                            Clone Existing NPC
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($sourceNpc)
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            Creating from: <strong>{{ $sourceNpc->name }}</strong>
            <a href="{{ route('npcs.create') }}" class="btn btn-sm btn-outline-secondary ms-2">Start Fresh</a>
        </div>
    @endif

    <!-- NPC Form -->
    <form action="{{ route('npcs.store') }}" method="POST" id="npcForm">
        @csrf
        @include('npcs._form', ['npc' => $sourceNpc, 'defaultIsTemplate' => $defaultIsTemplate])
        
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-danger btn-lg">
                <i class="bi bi-check-circle"></i> Create NPC
            </button>
            <a href="{{ route('npcs.index') }}" class="btn btn-secondary btn-lg">
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- Template Selection Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title">Select Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($templates->count() > 0)
                    <div class="list-group">
                        @foreach($templates as $template)
                            <a
                                href="{{ route('npcs.create', ['from_template' => $template->id]) }}"
                                class="list-group-item list-group-item-action"
                                data-template-hit-point-link
                                data-template-hit-point-choice-required="{{ $template->requiresTemplateHitPointChoice() ? 'true' : 'false' }}"
                                data-template-name="{{ $template->name }}"
                                data-template-hit-points="{{ $template->hit_points ?? '' }}"
                                data-template-hit-dice="{{ $template->hit_dice ?? '' }}"
                            >
                                <strong>{{ $template->name }}</strong>
                                <br>
                                <small class="text-muted">{{ $template->npc_type }} • CR {{ $template->challenge_rating ?? '0' }}</small>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center">No templates available yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Existing NPC Selection Modal -->
<div class="modal fade" id="existingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title">Select NPC to Clone</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Select an existing NPC to use as a starting point:</p>
                <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                    @php
                        $allNpcs = \App\Models\Npc::npcs()->orderBy('name')->get();
                    @endphp
                    @foreach($allNpcs as $existingNpc)
                        <a href="{{ route('npcs.create', ['from_npc' => $existingNpc->id]) }}" class="list-group-item list-group-item-action">
                            <strong>{{ $existingNpc->name }}</strong>
                            <br>
                            <small class="text-muted">{{ $existingNpc->npc_type }} • CR {{ $existingNpc->challenge_rating ?? '0' }}</small>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@include('templates.partials.hit-point-choice-modal')
@endsection
