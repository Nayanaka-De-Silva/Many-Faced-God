<div class="col-md-4 col-lg-3 mb-4" style="margin-left: {{ $level * 20 }}px;">
    <a href="{{ route('folders.show', $folder) }}" class="text-decoration-none">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="bi bi-folder"></i> {{ $folder->name }}
            </div>
            <div class="card-body">
                @if($folder->description)
                    <p class="card-text small text-muted">{{ Str::limit($folder->description, 100) }}</p>
                @endif
                @php($npcCount = $folder->actualNpcCount())
                @php($templateCount = $folder->templateCount())
                <p class="small mb-0">
                    <i class="bi bi-people"></i> {{ $npcCount }} {{ $npcCount === 1 ? 'NPC' : 'NPCs' }}
                    • <i class="bi bi-file-earmark-text"></i> {{ $templateCount }} {{ $templateCount === 1 ? 'Template' : 'Templates' }}
                    @if($folder->children->count() > 0)
                        • <i class="bi bi-folder"></i> {{ $folder->children->count() }} subfolders
                    @endif
                </p>
            </div>
        </div>
    </a>
</div>

@foreach($folder->children as $child)
    @include('folders._folder_card', ['folder' => $child, 'level' => $level + 1])
@endforeach
