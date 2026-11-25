<div>
    @if ($page === 'monitoring')
        <x-ph title="RFQ monitoring" :desc="'Every active request against its quotation deadline and the ' . $target . '-day award target.'" :crumbs="[['RFQ monitoring']]">
            <button type="button" class="btn" wire:click="export"><x-icon name="download" />Export</button>
        </x-ph>
        <x-strip :items="$strip" />
        <x-tabs :on="$tab" :tabs="['requests' => ['Active requests', $rows->count()], 'actions' => ['Actions required', $actions->count()]]" />
    @endif
    @if ($tab === 'requests')
        <x-card :flush="true">
            @if ($rows->isEmpty())<x-empty icon="check" title="No active requests" text="Open and closed requests that are not yet awarded appear here." />
            @else<div class="tbl-wrap"><table class="tbl"><thead><tr><th>RFQ</th><th>Stage</th><th class="hide-md">Owner</th><th>Due</th><th>SLA progress</th><th>Status</th><th class="num">Quotes</th></tr></thead><tbody>
                @foreach ($rows as $x)
                    <tr class="click" data-href="{{ $x['rfq']->status === 'closed' ? route('rfqs.compare', $x['rfq']) : route('rfqs.show', $x['rfq']) }}" tabindex="0"><td class="tmain"><div class="t1">{{ $x['rfq']->title }}</div><div class="t2 mono">{{ $x['rfq']->number }}</div></td><td>{{ $x['stage'] }}</td><td class="hide-md">{{ $x['rfq']->buyer->name }}</td>
                        <td style="white-space:nowrap">{{ $x['due']->format('j M, H:i') }}<div class="t2" @if ($x['late']) style="color:var(--bad)" @endif>{{ $x['due']->diffForHumans(['parts' => 1]) }}</div></td>
                        <td style="min-width:150px"><div class="row"><span class="mono sm" style="width:40px">{{ $x['pct'] }}%</span><div class="prog {{ $x['risk'][0] === 'ok' ? 'ok' : $x['risk'][0] }}" style="flex:1"><i style="width:{{ $x['pct'] }}%"></i></div></div></td>
                        <td><x-badge :status="$x['risk'][0]" :label="$x['risk'][1]" /></td><td class="num mono">{{ $x['rfq']->live_quotes_count }}<span class="faint">/{{ $x['rfq']->invitations_count }}</span></td></tr>
                @endforeach
            </tbody></table></div>@endif
        </x-card>
    @elseif ($tab === 'actions')
        <x-card title="What needs attention" :flush="true">
            @if ($actions->isEmpty())<x-empty icon="check" title="Nothing is overdue" text="Late orders, unmatched invoices and waiting approvals appear here." />
            @else<ul class="list">@foreach ($actions as [$ref, $url, $text, $sev])<li class="click" style="cursor:pointer" data-href="{{ $url }}" tabindex="0"><x-badge :status="$sev" :label="$sev === 'bad' ? 'Overdue' : 'Waiting'" /><div class="grow"><div class="t1">{{ $text }}</div><div class="muted sm mono">{{ $ref }}</div></div><x-icon name="right" /></li>@endforeach</ul>@endif
        </x-card>
    @else
        @php $canEdit = auth()->user()->allowed('system', 'E'); $last = $beat ? \Illuminate\Support\Carbon::parse($beat) : null; @endphp
        <x-ph title="SLA tracker" desc="Requests against their deadlines, and the reminders the system has sent. The check runs every hour." :crumbs="[['SLA tracker']]">
            <button type="button" class="btn" wire:click="export"><x-icon name="download" />Export</button>
            @perm('system', 'E')<button type="button" class="btn btn-p" wire:click="runReminders" wire:loading.attr="disabled"><x-icon name="refresh" />Run check now</button>@endperm
        </x-ph>
        <x-strip :items="$strip" />
        <div class="sla-run"><i class="sla-dot {{ ! $last || $last->lt(now()->subHours(2)) ? 'off' : '' }}"></i><div><b>{{ ! $last ? 'Hourly check has not run yet' : ($last->gte(now()->subHours(2)) ? 'Hourly check is running' : 'Hourly check has stopped') }}</b>@if ($last)<span>Last run {{ $last->diffForHumans() }}</span>@elseif ($canEdit)<span>Add the cron entry for "php artisan schedule:run".</span>@endif</div></div>
        <x-card title="Deadlines" sub="Most urgent first" :flush="true">
            <span class="sr">Reminders and deadlines</span>
            <div class="sla-bar"><div class="seg"><button wire:click="setShow('action')" class="{{ $show === 'action' ? 'on' : '' }}">Needs action {{ $rows->whereIn('risk.0', ['bad', 'warn'])->count() }}</button><button wire:click="setShow('ok')" class="{{ $show === 'ok' ? 'on' : '' }}">On track {{ $rows->where('risk.0', 'ok')->count() }}</button><button wire:click="setShow('all')" class="{{ $show === 'all' ? 'on' : '' }}">All {{ $rows->count() }}</button></div><div class="seg"><button wire:click="setStage('')" class="{{ $stage === '' ? 'on' : '' }}">Any stage</button><button wire:click="setStage('open')" class="{{ $stage === 'open' ? 'on' : '' }}">Quoting</button><button wire:click="setStage('closed')" class="{{ $stage === 'closed' ? 'on' : '' }}">Awaiting award</button></div><input class="input sla-search" type="search" placeholder="Search request or owner" wire:model.live.debounce.300ms="q"></div>
            @if ($rows->isEmpty())<x-empty icon="check" title="Nothing to track" text="Open and closed requests that are not yet awarded appear here." />
            @elseif ($deadlineRows->isEmpty())<x-empty icon="check" title="Nothing needs action" text="Every tracked request is on track."><button class="btn" wire:click="setShow('all')">Show all</button></x-empty>
            @else<div class="sla-table-wrap"><table class="sla-tbl"><colgroup><col class="sla-c-request"><col class="hide-md"><col class="sla-c-due"><col class="hide-sm"><col class="hide-md"><col class="sla-c-status"></colgroup><thead><tr><th>Request</th><th class="hide-md">Owner</th><th>Due</th><th class="hide-sm">Time used</th><th class="hide-md">Supplier reminder</th><th>Status</th></tr></thead><tbody>@foreach ($deadlineRows as $x) @php $r=$x['rfq']; $open=$r->status==='open'; $remindersFor = collect($historyText)->filter(fn($t) => $t['kind']==='rfq' && $t['ref']===$r->number); $at=$open ? $r->deadline_at->copy()->subDays($rules['sla_quote_reminder_days']) : null; @endphp
                <tr class="click" tabindex="0" data-href="{{ $open ? route('rfqs.show', $r) : route('rfqs.compare', $r) }}"><td class="tmain"><div class="t1 sla-title" title="{{ $r->title }}">{{ $r->title }}</div><div class="t2 mono">{{ $r->number }} · {{ $open ? 'Quoting' : 'Awaiting award' }} · {{ $r->live_quotes_count }}/{{ $r->invitations_count }} quotations</div></td><td class="hide-md">{{ $r->buyer->name }}</td><td><div>{{ $x['due']->format($open ? 'j M, H:i' : 'j M') }}</div><div class="t2" @if($x['late']) style="color:var(--bad)" @endif>{{ $x['late'] ? 'Overdue by '.($x['due']->diffInHours(now()) < 24 ? $x['due']->diffInHours(now()).' hours' : $x['due']->diffInDays(now()).' days') : 'in '.$x['due']->diffInDays(now()).' days' }}</div></td><td class="hide-sm"><div class="row"><span class="mono sm">{{ $x['pct'] }}%</span><div class="prog {{ $x['risk'][0] }}" style="flex:1"><i style="width:{{ $x['pct'] }}%"></i></div></div></td><td class="hide-md">@if ($open && $remindersFor->isNotEmpty()) Sent {{ $remindersFor->first()['created_at']->format('j M') }} to {{ $remindersFor->count() }} suppliers @elseif ($open) Due {{ $at->format('j M') }} @else - @endif</td><td><x-badge :status="$x['risk'][0]" :label="$x['risk'][1] === 'Fewer than two quotations' ? 'One quotation only' : $x['risk'][1]" /></td></tr>@endforeach</tbody></table></div><div class="sla-foot"><span class="muted sm">{{ $deadlineRows->firstItem() }}-{{ $deadlineRows->lastItem() }} of {{ $deadlineRows->total() }}</span><livewire:simple-pager :paginator="$deadlineRows" /></div>@endif
        </x-card>
        <div class="grid g-2-1" style="align-items:start">
            <x-card title="Reminder history" sub="Each reminder is sent once" :flush="true">
                <x-slot:act><select class="input" wire:model.live="kind"><option value="">All reminders</option><option value="rfq">Quotation deadline</option><option value="po">Purchase orders</option><option value="inv">Invoices</option><option value="pr">Requisitions</option><option value="doc">Supplier documents</option></select></x-slot:act>
                @if ($historyRows->isEmpty())
                    <div class="card-b faint">No reminders have been sent yet.</div>
                @else
                    <ul class="list sla-history">@foreach ($historyRows as $rm) @php $text=$historyText[$rm->id]; @endphp <li @if ($text['url']) class="click" tabindex="0" data-href="{{ $text['url'] }}" @endif><span class="sla-icon {{ $text['tone'] }}"><x-icon :name="$text['icon']" :size="16" /></span><div class="grow"><div class="t1">{{ $text['title'] }}</div><div class="t2">@if ($text['ref']){{ $text['ref'] }} · @endif sent to {{ $text['to'] }}</div></div><time class="muted sm">{{ $rm->created_at->isToday() ? 'Today, '.$rm->created_at->format('H:i') : $rm->created_at->format('j M, H:i') }}</time></li>@endforeach</ul>
                    <div class="card-b sla-foot"><livewire:simple-pager :paginator="$historyRows" /></div>
                @endif
            </x-card>
            <x-card title="SLA rules" sub="Used by the hourly check"><div class="stack">@foreach (\App\Support\Setting::SLA as $key => [$label,, $min,$max]) @php $f=str_replace('.','_',$key); $help=['sla.quote_reminder_days'=>'Remind invited suppliers this long before the deadline','sla.award_days'=>'Time allowed between closing a request and the award','sla.po_ack_days'=>'Remind the supplier when an order is not acknowledged','sla.requisition_days'=>'Remind approvers when a requisition waits longer'][$key]; @endphp <div class="sla-rule"><div><b>{{ $label }}</b><span>{{ $help }}</span></div><label class="sla-num"><input id="sr-{{ $f }}" class="input mono" type="number" min="{{ $min }}" max="{{ $max }}" wire:model="rules.{{ $f }}" @disabled(!$canEdit)><span>days</span></label>@error('rules.'.$f)<p class="err">{{ $message }}</p>@enderror</div>@endforeach @perm('system','E')<div><button class="btn btn-p" wire:click="saveRules">Save rules</button><span class="muted sm">Applies from the next check</span></div>@endperm<p class="help">Also fixed: a passed delivery date is reported at once, an approved invoice is flagged 3 days before it is due, and a supplier document 14 days before it expires.</p></div></x-card>
        </div>
    @endif
</div>
