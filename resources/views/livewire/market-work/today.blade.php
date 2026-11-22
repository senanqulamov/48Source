<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Today') }}</h1>
        <p class="ph-d">{{ now()->format('l, d M Y') }} · {{ __('Your assigned market tasks') }}</p></div></header>
    @php
        $done = $tasks->whereNotNull('completed_at')->count();
        $total = $tasks->count();
    @endphp
    <div class="card"><div class="card-b stack">
        <div class="row" style="justify-content:space-between">
            <strong>{{ $done }} {{ __('of') }} {{ $total }} {{ __('tasks complete') }}</strong>
            <span class="muted">{{ max(0,$total-$done) }} {{ __('remaining') }}</span>
        </div>
        <div class="prog" style="height:10px"><i style="width:{{ $total ? round($done/$total*100) : 0 }}%"></i></div>
    </div></div>
    <div class="grid g-2-1"><div class="stack">
        <section class="card"><div class="card-h"><h2 class="card-t">{{ __('My tasks') }}</h2></div>
            <div class="card-b stack">
                @forelse($tasks as $task)
                    <button type="button" class="source-worker-task {{ $task->completed_at ? 'is-done' : '' }}"
                        wire:key="worker-task-{{ $task->id }}" wire:click="toggle({{ $task->id }})"
                        aria-pressed="{{ $task->completed_at ? 'true' : 'false' }}">
                        <span class="source-task-check"><x-source.icon name="check" :size="20"/></span>
                        <span class="source-task-text"><strong>{{ $task->title }}</strong>
                            <small>{{ $task->market?->name ?? '—' }} · {{ $task->due_at?->format('d M H:i') ?? __('No due date') }}</small>
                            @if($task->details)<small>{{ $task->details }}</small>@endif
                        </span><span class="tag">{{ ucfirst($task->type) }}</span>
                    </button>
                @empty<div class="empty"><h3>{{ __('No assigned tasks') }}</h3>
                    <p>{{ __('Your seller can assign tasks for the markets you work in.') }}</p></div>@endforelse
            </div>
        </section>
    </div>
    <aside class="stack">
        <section class="card"><div class="card-h"><h2 class="card-t">{{ __('My markets') }}</h2></div>
            <div class="card-b stack">
                @forelse($markets as $market)
                    <div class="row"><x-source.icon name="store" :size="19" />
                        <span><strong>{{ $market->name }}</strong><small class="t2">{{ $market->location }} · {{ $market->products_count }} {{ __('products') }}</small></span></div>
                @empty<div class="empty">{{ __('No assigned markets') }}</div>@endforelse
            </div>
        </section>
        <div class="note">{{ __('Task completion is recorded for your seller. Updating order status and stock availability requires their separate approved workflow.') }}</div>
    </aside></div>
</div>