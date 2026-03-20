@extends('layouts.app')

@section('title', 'NPCs')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-people"></i> NPCs</h1>
        <a href="{{ route('npcs.create') }}" class="btn btn-danger">
            <i class="bi bi-plus-circle"></i> Create NPC
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('npcs.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Folder</label>
                    <select name="folder_id" class="form-select">
                        <option value="">All Folders</option>
                        <option value="root" {{ request('folder_id') == 'root' ? 'selected' : '' }}>
                            Root (Unfoldered)
                        </option>
                        @foreach($folders as $folder)
                            <option value="{{ $folder->id }}" {{ request('folder_id') == $folder->id ? 'selected' : '' }}>
                                {{ $folder->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">CR</label>
                    <select name="challenge_rating" class="form-select">
                        <option value="">Any CR</option>
                        @foreach(['0', '1/8', '1/4', '1/2', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'] as $cr)
                            <option value="{{ $cr }}" {{ request('challenge_rating') == $cr ? 'selected' : '' }}>
                                CR {{ $cr }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Alignment</label>
                    <select name="alignment" class="form-select">
                        <option value="">Any Alignment</option>
                        @foreach(\App\Models\Npc::ALIGNMENTS as $alignment)
                            <option value="{{ $alignment }}" {{ request('alignment') == $alignment ? 'selected' : '' }}>
                                {{ $alignment }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- NPC Cards Grid -->
    @if($npcs->count() > 0)
        <div class="row">
            @foreach($npcs as $npc)
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="npc-card card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <a href="{{ route('npcs.show', $npc) }}" class="text-decoration-none" style="flex: 1;">
                                <span>{{ $npc->name }}</span>
                            </a>
                            @if($npc->challenge_rating)
                                <span class="badge bg-dark">CR {{ $npc->challenge_rating }}</span>
                            @endif
                        </div>
                        <a href="{{ route('npcs.show', $npc) }}" class="text-decoration-none">
                            <div class="card-body">
                                <p class="small mb-1 text-muted fst-italic">
                                    {{ $npc->npc_type ?? 'Unknown Type' }}, {{ $npc->alignment ?? 'Unaligned' }}
                                </p>

                                @if($npc->notePreview())
                                    <p class="small text-muted npc-notes-preview mb-2">{{ $npc->notePreview() }}</p>
                                @endif
                                
                                <div class="stat-block">
                                    <div class="stat">
                                        <div class="stat-name">STR</div>
                                        <div class="stat-value">{{ $npc->strength }}</div>
                                        <div class="stat-modifier">({{ \App\Models\Npc::formatModifier($npc->strength_modifier) }})</div>
                                    </div>
                                    <div class="stat">
                                        <div class="stat-name">DEX</div>
                                        <div class="stat-value">{{ $npc->dexterity }}</div>
                                        <div class="stat-modifier">({{ \App\Models\Npc::formatModifier($npc->dexterity_modifier) }})</div>
                                    </div>
                                    <div class="stat">
                                        <div class="stat-name">CON</div>
                                        <div class="stat-value">{{ $npc->constitution }}</div>
                                        <div class="stat-modifier">({{ \App\Models\Npc::formatModifier($npc->constitution_modifier) }})</div>
                                    </div>
                                </div>
                                <div class="stat-block border-top-0">
                                    <div class="stat">
                                        <div class="stat-name">INT</div>
                                        <div class="stat-value">{{ $npc->intelligence }}</div>
                                        <div class="stat-modifier">({{ \App\Models\Npc::formatModifier($npc->intelligence_modifier) }})</div>
                                    </div>
                                    <div class="stat">
                                        <div class="stat-name">WIS</div>
                                        <div class="stat-value">{{ $npc->wisdom }}</div>
                                        <div class="stat-modifier">({{ \App\Models\Npc::formatModifier($npc->wisdom_modifier) }})</div>
                                    </div>
                                    <div class="stat">
                                        <div class="stat-name">CHA</div>
                                        <div class="stat-value">{{ $npc->charisma }}</div>
                                        <div class="stat-modifier">({{ \App\Models\Npc::formatModifier($npc->charisma_modifier) }})</div>
                                    </div>
                                </div>

                                @if($npc->hit_points)
                                    <p class="small mb-1">
                                        <strong>HP:</strong> {{ $npc->hit_points }}
                                        @if($npc->hit_dice)
                                            ({{ $npc->hit_dice }})
                                        @endif
                                    </p>
                                @endif

                                @if($npc->armor_class)
                                    <p class="small mb-0">
                                        <strong>AC:</strong> {{ $npc->armor_class }}
                                        @if($npc->armor_type)
                                            ({{ $npc->armor_type }})
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </a>
                        @if($npc->folder)
                            <div class="card-footer text-muted small d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-folder"></i> {{ $npc->folder->name }}</span>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#moveModal{{ $npc->id }}" onclick="event.stopPropagation();">
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                            </div>
                        @else
                            <div class="card-footer text-muted small d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-folder"></i> Root</span>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#moveModal{{ $npc->id }}" onclick="event.stopPropagation();">
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Move Modal -->
                    <div class="modal fade" id="moveModal{{ $npc->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Move "{{ $npc->name }}" to Folder</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="{{ route('npcs.move', $npc) }}" method="POST">
                                    @csrf
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="folder_id_{{ $npc->id }}" class="form-label">Select Folder</label>
                                            <select class="form-select" name="folder_id" id="folder_id_{{ $npc->id }}">
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
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center">
            {{ $npcs->withQueryString()->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-emoji-neutral" style="font-size: 4rem; color: #ccc;"></i>
            <h3 class="mt-3">No NPCs Found</h3>
            <p class="text-muted">Create your first NPC to get started!</p>
            <a href="{{ route('npcs.create') }}" class="btn btn-danger btn-lg">
                <i class="bi bi-plus-circle"></i> Create NPC
            </a>
        </div>
    @endif
</div>
@endsection
