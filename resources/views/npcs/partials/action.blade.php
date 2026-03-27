@if($action->isAttackAction())
    <div class="mb-3">
        <p class="mb-1">
            <strong><em>{{ $action->name }}.</em></strong>
        </p>

        @if($action->formatted_attack_line)
            <p class="mb-1">{{ $action->formatted_attack_line }}</p>
        @endif

        @if($action->formatted_hit_line)
            <p class="mb-1">{{ $action->formatted_hit_line }}</p>
        @endif

        @if($action->description)
            <p class="mb-0">{{ $action->description }}</p>
        @endif
    </div>
@elseif($action->action_type === \App\Models\NpcAction::TYPE_LEGENDARY_ACTION)
    <p>
        <strong><em>{{ $action->name }}{{ $action->legendary_cost > 1 ? ' (Costs ' . $action->legendary_cost . ' Actions)' : '' }}.</em></strong>
        {{ $action->description }}
    </p>
@else
    <p>
        <strong><em>{{ $action->name }}.</em></strong>
        {{ $action->description }}
    </p>
@endif
