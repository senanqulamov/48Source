<div>
    <x-ph :title="$page === 'sla' ? 'SLA tracker' : 'RFQ monitoring'" :desc="$page === 'sla' ? 'Reminders the system sends, when it last checked, and the rules behind each one.' : 'Every active request against its quotation deadline and the ' . $target . '-day award target.'" :crumbs="[[$page === 'sla' ? 'SLA tracker' : 'RFQ monitoring']]">
        <button type="button" class="btn" wire:click="export"><x-icon name="download" />Export</button>
    </x-ph>
    <x-strip :items="$strip" />
    @if ($page === 'monitoring')<x-tabs :on="$tab" :tabs="['requests' => ['Active requests', $rows->count()], 'actions' => ['Actions required', $actions->count()]]" />@endif
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
        <x-card title="Reminders" sub="Checked every hour. Each reminder is sent once.">
            <x-slot:act>@perm('system', 'E')<button type="button" class="btn btn-sm" wire:click="runReminders" wire:loading.attr="disabled"><x-icon name="refresh" :size="14" />Run check now</button>@endperm</x-slot:act>
            <dl class="kv" style="margin-bottom:14px"><dt>Scheduler last ran</dt><dd>{{ $beat ? \Illuminate\Support\Carbon::parse($beat)->diffForHumans() : 'Not yet. Add the cron entry for "php artisan schedule:run".' }}</dd></dl>
            <h3 class="lbl" style="margin-bottom:8px">Rules</h3>
            <ul style="margin:0 0 14px;padding-left:18px;display:grid;gap:4px"><li>A request closes within 24 hours and an invited supplier has not quoted: the supplier is reminded.</li><li>A purchase order is not acknowledged after 3 days: the supplier is reminded.</li><li>A delivery date has passed: buyer and supplier are told.</li><li>An approved invoice is due within 3 days: the buyer is reminded.</li><li>A requisition waits more than 2 days for approval: the approvers are reminded.</li><li>A supplier document expires within 14 days: the supplier is reminded.</li></ul>
            <h3 class="lbl" style="margin-bottom:8px">Sent so far</h3>
            @if ($reminders->isEmpty())<p class="faint">No reminders have been sent yet.</p>@else<div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>When</th><th>Rule and record</th></tr></thead><tbody>@foreach ($reminders as $r)<tr><td class="muted" style="white-space:nowrap">{{ $r->created_at->format('j M Y, H:i') }}</td><td class="mono">{{ $r->key }}</td></tr>@endforeach</tbody></table></div>@endif
        </x-card>
    @endif
</div>
