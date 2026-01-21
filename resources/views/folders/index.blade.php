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

    @if($folders->count() > 0)
        <div class="row">
            @foreach($folders as $folder)
                @include('folders._folder_card', ['folder' => $folder, 'level' => 0])
            @endforeach
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
</div>
@endsection
