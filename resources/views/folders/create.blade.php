@extends('layouts.app')

@section('title', 'Create Folder')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-folder-plus"></i> Create Folder</h1>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('folders.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Folder Name *</label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                required value="{{ old('name') }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Parent Folder</label>
                            <select name="parent_id" id="parent_id" class="form-select">
                                <option value="">-- None (Root Folder) --</option>
                                @foreach($parentFolders as $folder)
                                    <option value="{{ $folder->id }}" {{ (old('parent_id') ?? $parentId) == $folder->id ? 'selected' : '' }}>
                                        {{ $folder->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Create Folder
                            </button>
                            <a href="{{ route('folders.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
