<div>
    <x-ph :title="$page === 'sla' ? 'SLA tracker' : 'RFQ monitoring'" :desc="$page === 'sla' ? 'Deadline reminders and overdue requests. The deadline check runs every hour.' : 'Every active request against its quotation deadline and the ' . $target . '-day award target.'" :crumbs="[[$page === 'sla' ? 'SLA tracker' : 'RFQ monitoring']]">
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
        <div class="grid g-2-1">
            <x-card title="Reminders and deadlines" sub="Every tracked request against its deadline or award target" :flush="true">
                <x-slot:act>@perm('system', 'E')<button type="button" class="btn btn-sm" wire:click="runReminders" wire:loading.attr="disabled"><x-icon name="refresh" :size="14" />Run check now</button>@endperm</x-slot:act>
                @if ($rows->isEmpty())<x-empty icon="check" title="Nothing to track" text="Open and closed requests that are not yet awarded appear here." />
                @else<div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>RFQ</th><th>Deadline</th><th class="hide-sm">Reminder</th><th class="hide-sm">State</th><th>SLA</th></tr></thead><tbody>
                    @foreach ($rows as $x)
                        @php
                            $r = $x['rfq']; $open = $r->status === 'open';
                            $remindAt = $open ? $r->deadline_at->copy()->subDays($rules['sla_quote_reminder_days']) : null;
                            $reminder = ! $open ? 'Deadline passed' : ($remindAt->isPast() ? 'Sent to suppliers without a quotation' : 'Due ' . $remindAt->format('j M'));
                            $state = $open ? 'Collecting quotations' : 'Closed at deadline';
                            $sla = $x['risk'][0] === 'bad' ? ($open ? 'Overdue' : 'Breached') : ($x['risk'][0] === 'warn' ? 'At risk' : 'On track');
                        @endphp
                        <tr class="click" data-href="{{ $open ? route('rfqs.show', $r) : route('rfqs.compare', $r) }}" tabindex="0"><td class="tmain"><div class="t1">{{ $r->title }}</div><div class="t2 mono">{{ $r->number }}</div></td>
                            <td style="white-space:nowrap">{{ $x['due']->format('j M') }}<div class="t2" @if ($x['late']) style="color:var(--bad)" @endif>{{ $x['due']->diffForHumans(['parts' => 1]) }}</div></td>
                            <td class="hide-sm">{{ $reminder }}</td><td class="hide-sm">{{ $state }}</td><td><x-badge :status="$x['risk'][0]" :label="$sla" /></td></tr>
                    @endforeach
                </tbody></table></div>@endif
            </x-card>
            <div class="stack">
                <x-card title="SLA rules" sub="Used by the hourly check">
                    <div class="stack" style="gap:12px">
                        @foreach (\App\Support\Setting::SLA as $key => [$label, $default, $min, $max])
                            @php $f = str_replace('.', '_', $key); @endphp
                            <x-field :label="$label" :for="'sr-' . $f"><input id="sr-{{ $f }}" class="input mono" type="number" min="{{ $min }}" max="{{ $max }}" wire:model="rules.{{ $f }}" @disabled(! auth()->user()->allowed('system', 'E'))>@error('rules.' . $f)<p class="err">{{ $message }}</p>@enderror</x-field>
                        @endforeach
                        @perm('system', 'E')<div><button type="button" class="btn btn-p" wire:click="saveRules" wire:loading.attr="disabled">Save rules</button></div>@endperm
                        <p class="help">Also fixed: a passed delivery date is reported at once, an approved invoice is flagged 3 days before it is due, and a supplier document 14 days before it expires.</p>
                    </div>
                </x-card>
                <x-card title="Sent so far" :sub="'Scheduler last ran ' . ($beat ? \Illuminate\Support\Carbon::parse($beat)->diffForHumans() : 'never')" :flush="true">
                    @if (! $beat)<div class="card-b" style="border-bottom:1px solid var(--line)"><x-note kind="warn">The scheduler has not run yet. Add the cron entry for "php artisan schedule:run".</x-note></div>@endif
                    @if ($reminders->isEmpty())<div class="card-b faint">No reminders have been sent yet.</div>
                    @else<ul class="list">@foreach ($reminders->take(12) as $rm)<li data-plain><div class="grow"><div class="mono sm">{{ $rm->key }}</div><div class="muted sm">{{ $rm->created_at->format('j M Y, H:i') }}</div></div></li>@endforeach</ul>@endif
                </x-card>
            </div>
        </div>
    @endif
</div>
