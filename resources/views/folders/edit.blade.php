@extends('layouts.app')

@section('title', 'Edit ' . $folder->name)

@section('content')
<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-pencil"></i> Edit {{ $folder->name }}</h1>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('folders.update', $folder) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Folder Name *</label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                required value="{{ old('name', $folder->name) }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $folder->description) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Parent Folder</label>
                            <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">-- None (Root Folder) --</option>
                                @foreach($parentFolders as $parentFolder)
                                    <option value="{{ $parentFolder->id }}" {{ old('parent_id', $folder->parent_id) == $parentFolder->id ? 'selected' : '' }}>
                                        {{ $parentFolder->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                            <a href="{{ route('folders.show', $folder) }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
