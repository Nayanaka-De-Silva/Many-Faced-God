@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">
        <i class="bi bi-house-door"></i> Dashboard
    </h1>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-warning">NPCs</h5>
                            <h2 class="mb-0">{{ $npcCount }}</h2>
                        </div>
                        <i class="bi bi-people" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('npcs.index') }}" class="text-warning text-decoration-none">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-warning">Templates</h5>
                            <h2 class="mb-0">{{ $templateCount }}</h2>
                        </div>
                        <i class="bi bi-file-earmark-text" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('templates.index') }}" class="text-warning text-decoration-none">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-warning">Folders</h5>
                            <h2 class="mb-0">{{ $folderCount }}</h2>
                        </div>
                        <i class="bi bi-folder" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('folders.index') }}" class="text-warning text-decoration-none">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-warning">
                    <i class="bi bi-lightning"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3 col-6">
                            <a href="{{ route('npcs.create') }}" class="btn btn-outline-danger w-100">
                                <i class="bi bi-plus-circle"></i> Create NPC
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <form action="{{ route('npcs.generate') }}" method="POST" class="d-inline w-100">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-dice-5"></i> Generate Random
                                </button>
                            </form>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('templates.index') }}" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-file-earmark-text"></i> From Template
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('folders.create') }}" class="btn btn-outline-success w-100">
                                <i class="bi bi-folder-plus"></i> New Folder
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent NPCs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-warning">
                    <i class="bi bi-clock-history"></i> Recently Updated NPCs
                </div>
                <div class="card-body">
                    @if($recentNpcs->count() > 0)
                        <div class="row">
                            @foreach($recentNpcs as $npc)
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
                                                </p>
                                                @if($npc->challenge_rating)
                                                    <span class="badge badge-cr">CR {{ $npc->challenge_rating }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center mb-0">
                            <i class="bi bi-emoji-neutral"></i> No NPCs yet. Create your first one!
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
