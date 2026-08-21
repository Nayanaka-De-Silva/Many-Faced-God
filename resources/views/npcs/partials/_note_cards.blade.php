{{-- Note cards stack — view mode only. Used by npcs/show and templates/show. --}}
@foreach($npc->noteCards as $note)
    <div class="note-card mb-2">
        @if(filled($note->description))
            <button class="btn btn-link note-card-title w-100 text-start px-0"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#note-card-{{ $note->id }}"
                    aria-expanded="false">
                {{ $note->title }}
                <i class="bi bi-chevron-down ms-1"></i>
            </button>
            <div id="note-card-{{ $note->id }}" class="collapse">
                <div class="npc-notes-content ps-2 pt-1">{{ $note->description }}</div>
            </div>
        @else
            <div class="note-card-title-only fw-semibold">{{ $note->title }}</div>
        @endif
    </div>
@endforeach
