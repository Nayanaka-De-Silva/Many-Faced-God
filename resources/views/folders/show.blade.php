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
        <div class="row mb-4">
            @foreach($folder->children as $child)
                <div class="col-md-3 mb-3">
                    <a href="{{ route('folders.show', $child) }}" class="text-decoration-none">
                        <div class="card">
                            <div class="card-body">
                                <i class="bi bi-folder text-success"></i> {{ $child->name }}
                                <br>
                                <small class="text-muted">{{ $child->npcs->count() }} NPCs</small>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <!-- NPCs in this folder -->
    <h5 class="mb-3"><i class="bi bi-people"></i> NPCs in this Folder</h5>
    @if($folder->npcs->count() > 0)
        <div class="row">
            @foreach($folder->npcs as $npc)
                <div class="col-md-4 col-lg-3 mb-3">
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
        <p class="text-muted text-center">No NPCs in this folder yet.</p>
    @endif
</div>
@endsection
