@extends('layouts.app')

@section('title', 'Edit ' . $npc->name)

@section('content')
<div class="container-fluid">
    @php $isTemplate = $npc->is_template; @endphp

    <h1 class="mb-4">
        <i class="bi bi-pencil"></i>
        @if($isTemplate)
            Edit Template {{ $npc->name }}
            <span class="badge bg-secondary">Template</span>
        @else
            Edit {{ $npc->name }}
        @endif
    </h1>

    <form action="{{ route('npcs.update', $npc) }}" method="POST" id="npcForm">
        @csrf
        @method('PUT')
        {{-- Grays the form's section headers so template edits are visually distinct --}}
        <div @class(['template-mode' => $isTemplate])>
            @include('npcs._form', ['npc' => $npc])
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> Save Changes
            </button>
            <a href="{{ $isTemplate ? route('templates.show', $npc) : route('npcs.show', $npc) }}" class="btn btn-secondary btn-lg">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
