<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('Market-worker tasks') }}</h1>
            <p class="ph-d">{{ __('Assign real work to people responsible for your markets.') }}</p></div>
        <div class="ph-a"><button class="btn btn-p" type="button" wire:click="$set('creating',true)"><x-source.icon name="plus" :size="16"/>{{ __('Assign task') }}</button></div>
    </header>
    <section class="card">
        <div class="card-h"><h2 class="card-t">{{ __('Assigned tasks') }}</h2></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Market') }}</th><th>{{ __('Worker') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead>
            <tbody>@forelse($tasks as $task)
                <tr wire:key="seller-task-{{ $task->id }}">
                    <td><div class="t1">{{ $task->title }}</div><div class="t2">{{ ucfirst($task->type) }} · {{ $task->details }}</div></td>
                    <td>{{ $task->market?->name ?? '—' }}</td>
                    <td>{{ $task->worker?->name ?? '—' }}</td>
                    <td class="mono sm">{{ $task->due_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td><span class="badge {{ $task->completed_at ? 'b-ok' : 'b-info' }}">{{ $task->completed_at ? __('Done') : __('Assigned') }}</span></td>
                    <td>@unless($task->completed_at)
                        <button type="button" class="btn btn-sm btn-d" wire:click="removeTask({{ $task->id }})" wire:confirm="{{ __('Remove this task?') }}">{{ __('Remove') }}</button>
                    @endunless</td>
                </tr>
            @empty<tr><td colspan="6"><div class="empty">{{ __('No tasks assigned yet.') }}</div></td></tr>@endforelse</tbody>
        </table></div><div class="dt-foot">{{ $tasks->links() }}</div>
    </section>
    @if($creating)
        <div class="scrim source-dialog" wire:key="seller-new-market-task" wire:keydown.escape="$set('creating',false)" wire:click.self="$set('creating',false)">
            <section class="modal wide" role="dialog" aria-modal="true" aria-labelledby="market-task-title">
                <div class="ov-h"><h2 id="market-task-title">{{ __('Assign market task') }}</h2><span class="sp"></span><button class="icon-btn" type="button" wire:click="$set('creating',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x"/></button></div>
                <form wire:submit="createTask">
                    <div class="ov-b stack">
                        <div class="fgrid">
                            <div class="field"><label for="task-market">{{ __('Market') }}</label>
                                <select class="select" id="task-market" wire:model="marketId" required><option value="">{{ __('Choose market') }}</option>
                                @foreach($markets as $market)<option value="{{ $market->id }}">{{ $market->name }}</option>@endforeach</select>
                                @error('marketId')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field"><label for="task-worker">{{ __('Worker') }}</label>
                                <select class="select" id="task-worker" wire:model="workerId" required><option value="">{{ __('Choose assigned worker') }}</option>
                                @foreach($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->name }}</option>@endforeach</select>
                                @error('workerId')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field"><label for="task-type">{{ __('Type') }}</label>
                                <select class="select" id="task-type" wire:model="type">
                                    @foreach(['order'=>'Order','availability'=>'Availability','price'=>'Price'] as $value => $label)
                                        <option value="{{ $value }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>@error('type')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field"><label for="task-due">{{ __('Due date') }}</label><input class="input" id="task-due" type="datetime-local" wire:model="dueAt"/>
                                @error('dueAt')<span class="err">{{ $message }}</span>@enderror</div>
                        </div>
                        <div class="field"><label for="task-name">{{ __('Task title') }}</label>
                            <input class="input" id="task-name" wire:model="title" maxlength="160" required/>@error('title')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="task-detail">{{ __('Instructions') }}</label><textarea class="textarea" id="task-detail" wire:model="details" rows="3"></textarea>
                            @error('details')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="note">{{ __('Task completion records the worker action. It does not automatically change order or stock values.') }}</div>
                    </div>
                    <div class="ov-f"><button class="btn" type="button" wire:click="$set('creating',false)">{{ __('Cancel') }}</button>
                        <button class="btn btn-p" type="submit" wire:loading.attr="disabled">{{ __('Assign task') }}</button></div>
                </form>
            </section>
        </div>
    @endif
</div>