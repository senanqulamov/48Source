<div>
    <x-ph :title="$seller ? 'Workers' : 'Field workers'" :desc="$seller ? 'Market workers of ' . $place->name . '. They pick and deliver; they cannot change products, prices or other workers.' : 'People of ' . $place->name . ' who carry out site surveys and inspections for buyers.'" :crumbs="[[$seller ? 'Workers' : 'Field workers']]">
        <button type="button" class="btn btn-p" wire:click="open('add')"><x-icon name="plus" />Add worker</button>
    </x-ph>
    <div class="stack">
        <x-card :title="$seller ? 'Market workers' : 'Field workers'" :sub="$workers->count() . ' in total'" :flush="true">
            @if ($workers->isEmpty())<x-empty icon="users" title="No workers yet" text="Add a worker. They get an email with a link to set their password." />
            @else<div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Worker</th><th>Status</th><th class="num">{{ $seller ? 'Tasks done' : 'Open assignments' }}</th><th class="hide-sm">Last active</th><th style="width:130px"></th></tr></thead><tbody>
                @foreach ($workers as $w)<tr wire:key="w-{{ $w->id }}"><td class="tmain"><div class="row"><span class="avatar av{{ $w->id % 5 }}" aria-hidden="true">{{ $w->initials() }}</span><div><div class="t1">{{ $w->name }}</div><div class="t2">{{ $w->email }}</div></div></div></td><td><x-badge :status="$w->status" /></td><td class="num mono">{{ $load[$w->id] ?? 0 }}</td><td class="hide-sm muted">{{ $w->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="num">@if ($w->status !== 'invited')<button type="button" class="btn btn-sm {{ $w->status === 'disabled' ? '' : 'btn-d' }}" wire:click="toggle({{ $w->id }})" wire:confirm="{{ $w->status === 'disabled' ? 'Let ' . $w->name . ' sign in again?' : 'Stop ' . $w->name . ' from signing in?' }}">{{ $w->status === 'disabled' ? 'Reactivate' : 'Deactivate' }}</button>@endif</td></tr>@endforeach
            </tbody></table></div>@endif
        </x-card>
        @unless ($seller)
            <x-card title="Open assignments" sub="Field assessments the buyer is waiting for" :flush="true">
                @if ($open->isEmpty())<div class="card-b faint">No open field assessments.</div>
                @else<div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Assignment</th><th>Reference</th><th>Due</th><th style="width:320px">Evaluator</th></tr></thead><tbody>
                    @foreach ($open as $a)<tr wire:key="fa-{{ $a->id }}"><td class="tmain"><a class="t1" href="{{ route('assessments.show', $a) }}" wire:navigate style="color:inherit">{{ $a->title }}</a><div class="t2">{{ $a->statusLabel() }}</div></td><td class="mono">{{ $a->number }}{{ $a->rfq ? ' · ' . $a->rfq->number : '' }}</td><td style="white-space:nowrap">{{ $a->due_on?->format('j M Y') ?? '-' }}</td>
                        <td><div class="row"><div style="flex:1"><select class="select" wire:model="assign.{{ $a->id }}" aria-label="Evaluator for {{ $a->number }}"><option value="">{{ $a->assignee?->name ?? 'Not assigned' }}</option>@foreach ($people as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><button type="button" class="btn btn-sm" wire:click="assignTo({{ $a->id }})">Assign</button></div></td></tr>@endforeach
                </tbody></table></div>@endif
            </x-card>
        @endunless
    </div>
    @if ($modal === 'add')
        <x-modal title="Add a worker"><div class="stack" style="gap:14px"><x-field label="Name" for="wk-n"><input id="wk-n" class="input" wire:model="w.name" autofocus></x-field><x-field label="Email" for="wk-e" help="They receive a link to set their own password."><input id="wk-e" type="email" class="input" wire:model="w.email"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="add" wire:loading.attr="disabled">Add worker</button></x-slot:foot></x-modal>
    @endif
</div>
