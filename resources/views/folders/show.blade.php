@extends('layouts.app')

@section('title', $folder->name)

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('folders.index') }}">Folders</a></li>
            @foreach($folder->breadcrumb as $crumb)
                @if($loop->last)
                    <li class="breadcrumb-item active">{{ $crumb->name }}</li>
                @else
                    <li class="breadcrumb-item"><a href="{{ route('folders.show', $crumb) }}">{{ $crumb->name }}</a></li>
                @endif
            @endforeach
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-folder"></i> {{ $folder->name }}</h1>
        <div class="btn-group">
            <a href="{{ route('folders.edit', $folder) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <a href="{{ route('folders.create', ['parent_id' => $folder->id]) }}" class="btn btn-success">
                <i class="bi bi-folder-plus"></i> New Subfolder
            </a>
            <form action="{{ route('folders.destroy', $folder) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this folder? NPCs will be moved to the parent folder.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    @if($folder->description)
        <p class="text-muted mb-4">{{ $folder->description }}</p>
    @endif

    <!-- Subfolders -->
    @if($folder->children->count() > 0)
        <h5 class="mb-3"><i class="bi bi-folder"></i> Subfolders</h5>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3 mb-4">
            @foreach($folder->children as $child)
                @php($childNpcCount = $child->actualNpcCount())
                @php($childTemplateCount = $child->templateCount())
                <div class="col">
                    <a href="{{ route('folders.show', $child) }}" class="text-decoration-none">
                        <div class="card">
                            <div class="card-body">
                                <i class="bi bi-folder text-success"></i> {{ $child->name }}
                                <br>
                                <small class="text-muted">
                                    {{ $childNpcCount }} {{ $childNpcCount === 1 ? 'NPC' : 'NPCs' }}
                                    • {{ $childTemplateCount }} {{ $childTemplateCount === 1 ? 'Template' : 'Templates' }}
                                </small>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <!-- NPCs in this folder -->
    <h5 class="mb-3"><i class="bi bi-people"></i> NPCs in this Folder</h5>
    @if($folder->actualNpcs->count() > 0)
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3 mb-4">
            @foreach($folder->actualNpcs as $npc)
                <div class="col">
                    <a href="{{ route('npcs.show', $npc) }}" class="text-decoration-none">
                        <div class="npc-card card h-100">
                            <div class="card-header">
                                {{ $npc->name }}
                            </div>
                            <div class="card-body">
                                <p class="card-text small mb-1">
                                    <strong>{{ $npc->npc_type ?? 'Unknown Type' }}</strong>
                                </p>
                                <p class="card-text small mb-1">
                                    {{ $npc->alignment ?? 'Unaligned' }}
                                    @if($npc->challenge_rating)
                                        • CR {{ $npc->challenge_rating }}
                                    @endif
                                </p>
                                @if($npc->notePreview())
                                    <p class="card-text small text-muted npc-notes-preview mb-0">
                                        {{ $npc->notePreview(120) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-muted text-center mb-4">No NPCs in this folder yet.</p>
    @endif

    <!-- Templates in this folder -->
    <h5 class="mb-3"><i class="bi bi-file-earmark-text"></i> Templates in this Folder</h5>
    @if($folder->templates->count() > 0)
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
            @foreach($folder->templates as $template)
                <div class="col">
                    <a href="{{ route('templates.show', $template) }}" class="text-decoration-none">
                        <div class="card h-100 border-secondary">
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
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-muted text-center">No templates in this folder yet.</p>
    @endif
</div>
@endsection
