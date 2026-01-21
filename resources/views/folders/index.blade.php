@extends('layouts.app')

@section('title', 'Folders')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-folder"></i> Folders</h1>
        <a href="{{ route('folders.create') }}" class="btn btn-success">
            <i class="bi bi-folder-plus"></i> New Folder
        </a>
    </div>

    <!-- Root Folder -->
    @if($rootNpcs->count() > 0)
        <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-inbox"></i> Root (Unfoldered)</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <a href="{{ route('npcs.index', ['folder_id' => 'root']) }}" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body">
                                <i class="bi bi-inbox text-muted"></i> Root Folder
                                <br>
                                <small class="text-muted">{{ $rootNpcs->count() }} NPCs</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if($folders->count() > 0)
        <div class="row">
            @foreach($folders as $folder)
                @include('folders._folder_card', ['folder' => $folder, 'level' => 0])
            @endforeach
        </div>
    @else
        @if($rootNpcs->count() > 0)
            <div class="text-center py-5">
                <i class="bi bi-folder" style="font-size: 4rem; color: #ccc;"></i>
                <h3 class="mt-3">No Custom Folders</h3>
                <p class="text-muted">Create folders to organize your NPCs.</p>
                <a href="{{ route('folders.create') }}" class="btn btn-success btn-lg">
                    <i class="bi bi-folder-plus"></i> Create First Folder
                </a>
            </div>
        @else
            <div class="text-center py-5">
                <i class="bi bi-folder" style="font-size: 4rem; color: #ccc;"></i>
                <h3 class="mt-3">No Folders Yet</h3>
                <p class="text-muted">Organize your NPCs into folders.</p>
                <a href="{{ route('folders.create') }}" class="btn btn-success btn-lg">
                    <i class="bi bi-folder-plus"></i> Create First Folder
                </a>
            </div>
        @endif
    @endif
</div>
@endsection
