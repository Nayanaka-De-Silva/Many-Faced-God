@extends('layouts.app')

@section('title', 'Templates')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-file-earmark-text"></i> NPC Templates</h1>
        <a href="{{ route('npcs.create', ['is_template' => 1]) }}" class="btn btn-danger">
            <i class="bi bi-plus-circle"></i> Create Template
        </a>
    </div>

    <p class="text-muted mb-4">
        Templates are reusable NPC blueprints. Create an NPC and check "Save as Template" to add it here.
    </p>

    @if($templates->count() > 0)
        <div class="row">
            @foreach($templates as $template)
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-earmark-text"></i> {{ $template->name }}</span>
                            @if($template->challenge_rating)
                                <span class="badge bg-dark">CR {{ $template->challenge_rating }}</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <p class="small text-muted fst-italic mb-2">
                                {{ $template->npc_type ?? 'Unknown Type' }}, {{ $template->alignment ?? 'Unaligned' }}
                            </p>
                            
                            @if($template->hit_points)
                                <p class="small mb-1">
                                    <strong>HP:</strong> {{ $template->hit_points }}
                                </p>
                            @endif

                            @if($template->armor_class)
                                <p class="small mb-0">
                                    <strong>AC:</strong> {{ $template->armor_class }}
                                </p>
                            @endif
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100">
                                <a href="{{ route('templates.show', $template) }}" class="btn btn-outline-secondary btn-sm">
                                    View
                                </a>
                                <a
                                    href="{{ route('npcs.create', ['from_template' => $template->id]) }}"
                                    class="btn btn-outline-danger btn-sm"
                                    data-template-hit-point-link
                                    data-template-hit-point-choice-required="{{ $template->requiresTemplateHitPointChoice() ? 'true' : 'false' }}"
                                    data-template-name="{{ $template->name }}"
                                    data-template-hit-points="{{ $template->hit_points ?? '' }}"
                                    data-template-hit-dice="{{ $template->hit_dice ?? '' }}"
                                >
                                    Use Template
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-center">
            {{ $templates->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #ccc;"></i>
            <h3 class="mt-3">No Templates Yet</h3>
            <p class="text-muted">Create an NPC and check "Save as Template" to add it here.</p>
            <a href="{{ route('npcs.create', ['is_template' => 1]) }}" class="btn btn-danger btn-lg">
                <i class="bi bi-plus-circle"></i> Create Template
            </a>
        </div>
    @endif
</div>

@include('templates.partials.hit-point-choice-modal')
@endsection
