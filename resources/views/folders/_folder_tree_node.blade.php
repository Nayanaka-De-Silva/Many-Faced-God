<div class="folder-tree-node" @if($level === 0) data-folder-root-node @endif>
    <div class="folder-tree-row d-flex align-items-center gap-2 py-2">
        @if($folder->allChildrenWithCounts->count() > 0)
            <button class="btn btn-sm btn-link p-0 text-muted"
                    data-bs-toggle="collapse"
                    data-bs-target="#folder-children-{{ $folder->id }}"
                    aria-expanded="true">
                <i class="bi bi-chevron-down"></i>
            </button>
        @else
            <span style="display:inline-block;width:1.5rem;"></span>
        @endif
        <i class="bi bi-folder text-success"></i>
        <a href="{{ route('folders.show', $folder) }}" class="text-decoration-none fw-semibold">
            {{ $folder->name }}
        </a>
        <small class="text-muted">
            @php($npcCount = $folder->actualNpcCount())
            @php($templateCount = $folder->templateCount())
            {{ $npcCount }} {{ $npcCount === 1 ? 'NPC' : 'NPCs' }}
            &bull; {{ $templateCount }} {{ $templateCount === 1 ? 'Template' : 'Templates' }}
        </small>
        <div class="ms-auto d-flex gap-1">
            <a href="{{ route('folders.edit', $folder) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i>
            </a>
            <a href="{{ route('folders.create', ['parent_id' => $folder->id]) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-folder-plus"></i>
            </a>
        </div>
    </div>
    @if($folder->allChildrenWithCounts->count() > 0)
        <div class="collapse show" id="folder-children-{{ $folder->id }}">
            <div class="folder-tree-children">
                @foreach($folder->allChildrenWithCounts as $child)
                    @include('folders._folder_tree_node', ['folder' => $child, 'level' => $level + 1])
                @endforeach
            </div>
        </div>
    @endif
</div>
