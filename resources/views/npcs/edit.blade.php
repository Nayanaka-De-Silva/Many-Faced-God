@extends('layouts.app')

@section('title', 'Edit ' . $npc->name)

@section('content')
<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-pencil"></i> Edit {{ $npc->name }}</h1>

    <form action="{{ route('npcs.update', $npc) }}" method="POST" id="npcForm">
        @csrf
        @method('PUT')
        @include('npcs._form', ['npc' => $npc])
        
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> Save Changes
            </button>
            <a href="{{ route('npcs.show', $npc) }}" class="btn btn-secondary btn-lg">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
