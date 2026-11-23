@php use App\Support\Money; use App\Models\SupplierDocument; $rk = ['low' => ['ok', 'Low risk'], 'medium' => ['warn', 'Medium risk'], 'high' => ['bad', 'High risk']][$risk['level']]; $latest = $s->scorecards->sortByDesc('period')->first(); @endphp
<div>
    <x-ph :title="$s->name" :crumbs="$sourcing ? [['Suppliers', route('suppliers.index')], [$s->name]] : [['Company profile']]" :recent="$s->name" recent-sub="Supplier 360">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><x-badge :status="$s->status" :label="$s->statusLabel()" /><x-badge :status="$rk[0]" :label="$rk[1]" />@if ($s->category)<span class="tag">{{ $s->category->name }}</span>@endif @if ($s->city)<span class="tag">{{ $s->city }}</span>@endif</div></x-slot:pre>
        @if ($sourcing)
            @perm('field', 'A')<button type="button" class="btn" wire:click="open('fa')"><x-icon name="camera" />Field assessment</button>@endperm
            @perm('suppliers', 'E')<button type="button" class="btn" wire:click="open('card')"><x-icon name="star" />Scorecard</button>@endperm
            <a class="btn" href="{{ route('messages.index') }}" wire:navigate><x-icon name="mail" />Messages</a>
        @endif
    </x-ph>
    <x-facts :items="[['Awarded value', Money::format($stats['spend'], 'USD', false), $stats['orders'] . ' order' . ($stats['orders'] === 1 ? '' : 's'), true], ['On-time delivery', $stats['ontime'] === null ? 'No deliveries yet' : $stats['ontime'] . '%', '', true], ['Response rate', $stats['invited'] ? min(100, intdiv($stats['quoted'] * 100, $stats['invited'])) . '%' : 'Not invited yet', $stats['invited'] ? $stats['quoted'] . ' of ' . $stats['invited'] : '', true], ['Latest scorecard', $latest ? number_format($latest->score_tenths / 10, 1) . ' of 5' : 'None yet', $latest?->period ?? '', true], ['Rating', $s->rating() . ' of 5', '', true]]" />
    <x-tabs :on="$tab" :tabs="['overview' => 'Overview', 'docs' => ['Documents', $s->documents->count()], 'cards' => ['Scorecards', $s->scorecards->count()], 'actions' => ['Action plan', $s->actions->where('status', 'open')->count()], 'history' => 'History']" />
    @if ($tab === 'overview')
        <div class="grid g-2-1">
            <div class="stack">
                @php
                    $has = fn ($type) => (bool) $s->documents->first(fn ($d) => $d->type === $type && $d->status === 'verified' && ! $d->isExpired());
                    $steps = [['Account approved', $s->status === 'active'], ['Contact name, email and phone on file', $s->contact_name && $s->email && $s->phone], ['Company registration verified', $has('registration')], ['Tax certificate verified', $has('tax')], ['Bank details letter verified', $has('bank')], ['First scorecard recorded', $s->scorecards->isNotEmpty()]];
                    $doneSteps = collect($steps)->where(1, true)->count();
                @endphp
                <x-card title="Qualification" :sub="$doneSteps . ' of ' . count($steps) . ' steps complete'">
                    <x-slot:act><x-badge :status="$doneSteps === count($steps) ? 'ok' : ($doneSteps >= 3 ? 'warn' : 'draft')" :label="$doneSteps === count($steps) ? 'Fully qualified' : ($doneSteps >= 3 ? 'Partly qualified' : 'Onboarding')" /></x-slot:act>
                    <div class="prog {{ $doneSteps === count($steps) ? 'ok' : '' }}" style="margin-bottom:12px"><i style="width:{{ intdiv($doneSteps * 100, count($steps)) }}%"></i></div>
                    <ol class="steps-v">@foreach ($steps as [$label, $ok])<li class="{{ $ok ? 'done' : '' }}"><b @unless ($ok) style="font-weight:500;color:var(--text-2)" @endunless>{{ $label }}</b></li>@endforeach</ol>
                    @if ($doneSteps < count($steps))<p class="help" style="margin-top:10px">{{ $sourcing ? 'Missing documents can be added by you or by the supplier under Documents.' : 'Upload the missing documents under Documents. The buyer verifies them.' }}</p>@endif
                </x-card>
                <x-card title="Risk" :sub="$rk[1] . ' · ' . $risk['points'] . ' points'">
                    @if (! $risk['reasons'])<p class="muted">Nothing to worry about: documents are in order, deliveries are on time and no action is overdue.</p>
                    @else<ul style="margin:0;padding-left:18px;display:grid;gap:4px">@foreach ($risk['reasons'] as $r)<li>{{ $r }}</li>@endforeach</ul>@endif
                </x-card>
                <x-card title="Contracts" :flush="true">
                    @if ($s->contracts->where('status', '!=', 'draft')->isEmpty())<div class="card-b faint">No contracts with this supplier.</div>
                    @else<ul class="list">@foreach ($s->contracts->where('status', '!=', 'draft') as $c)<li class="click" style="cursor:pointer" data-href="{{ route('contracts.show', $c) }}" tabindex="0"><div class="grow"><div class="t1">{{ $c->title }}</div><div class="muted sm"><span class="mono">{{ $c->number }}</span> · until {{ $c->ends_on->format('j M Y') }}</div></div><x-badge :status="['active' => 'ok', 'expired' => 'warn', 'terminated' => 'bad'][$c->status] ?? 'draft'" :label="$c->statusLabel()" /><x-icon name="right" /></li>@endforeach</ul>@endif
                </x-card>
                <x-card title="Field assessments" :flush="true">
                    @if ($s->assessments->isEmpty())<div class="card-b faint">None requested.</div>
                    @else<ul class="list">@foreach ($s->assessments->sortByDesc('id')->take(6) as $a)<li class="click" style="cursor:pointer" data-href="{{ route('assessments.show', $a) }}" tabindex="0"><div class="grow"><div class="t1">{{ $a->title }}</div><div class="muted sm mono">{{ $a->number }}</div></div><x-badge :status="['submitted' => 'info', 'approved' => 'ok', 'rejected' => 'bad', 'in_progress' => 'warn'][$a->status] ?? 'draft'" :label="$a->statusLabel()" /><x-icon name="right" /></li>@endforeach</ul>@endif
                </x-card>
            </div>
            <div class="stack">
                <x-card title="Company"><dl class="kv" style="grid-template-columns:120px 1fr"><dt>Contact</dt><dd>{{ $s->contact_name ?? '-' }}</dd><dt>Email</dt><dd>{{ $s->email ?? '-' }}</dd><dt>Phone</dt><dd>{{ $s->phone ?? '-' }}</dd><dt>City</dt><dd>{{ $s->city ?? '-' }}</dd><dt>Since</dt><dd>{{ $s->created_at->format('j M Y') }}</dd></dl></x-card>
                <x-card title="People" :flush="true"><ul class="list">@forelse ($s->users as $usr)<li><span class="avatar av{{ $usr->id % 5 }}" aria-hidden="true">{{ $usr->initials() }}</span><div class="grow"><div class="t1">{{ $usr->name }}</div><div class="muted sm">{{ $usr->roleLabel() }}</div></div><x-badge :status="$usr->status" /></li>@empty<li class="faint">No user accounts.</li>@endforelse</ul></x-card>
            </div>
        </div>
    @elseif ($tab === 'docs')
        <x-card title="Documents" sub="Certificates and registrations, with their expiry dates" :flush="true">
            <x-slot:act><button type="button" class="btn btn-sm" wire:click="open('doc')"><x-icon name="upload" :size="14" />Add document</button></x-slot:act>
            @if ($s->documents->isEmpty())<x-empty icon="clip" title="No documents yet" text="Add the company registration first. It lowers the risk rating once verified." />
            @else
                <div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Document</th><th>Kind</th><th>Expires</th><th>Status</th><th></th></tr></thead><tbody>
                    @foreach ($s->documents->sortByDesc('id') as $d)
                        <tr wire:key="d-{{ $d->id }}"><td class="tmain"><div class="t1">{{ $d->title }}</div><div class="t2">{{ $d->original_name ?? 'No file attached' }}</div></td><td><span class="tag">{{ SupplierDocument::TYPES[$d->type] ?? $d->type }}</span></td>
                            <td style="white-space:nowrap">@if ($d->expires_on){{ $d->expires_on->format('j M Y') }}@if ($d->isExpired())<div class="t2" style="color:var(--bad)">expired</div>@elseif ($d->expiresSoon())<div class="t2" style="color:var(--warn)">{{ $d->expires_on->diffForHumans(['parts' => 1]) }}</div>@endif @else<span class="faint">No expiry</span>@endif</td>
                            <td><x-badge :status="['verified' => 'ok', 'rejected' => 'bad', 'pending' => 'warn'][$d->status]" :label="ucfirst($d->status === 'pending' ? 'To check' : $d->status)" /></td>
                            <td class="num"><div class="row" style="justify-content:flex-end">@if ($d->path)<a class="btn btn-sm btn-g" href="{{ route('suppliers.document', [$s->id, $d->id]) }}"><x-icon name="download" :size="14" />Open</a>@endif
                                @if ($sourcing && $d->status === 'pending')@perm('suppliers', 'A')<button type="button" class="btn btn-sm btn-p" wire:click="verify({{ $d->id }}, true)">Verify</button><button type="button" class="btn btn-sm btn-d" wire:click="verify({{ $d->id }}, false)">Reject</button>@endperm @endif</div></td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </x-card>
    @elseif ($tab === 'cards')
        <x-card title="Scorecards" sub="One per quarter. The rating is the average of the last four." :flush="true">
            <x-slot:act>@if ($sourcing)@perm('suppliers', 'E')<button type="button" class="btn btn-sm" wire:click="open('card')"><x-icon name="plus" :size="14" />New scorecard</button>@endperm @endif</x-slot:act>
            @if ($s->scorecards->isEmpty())<x-empty icon="star" title="No scorecards yet" text="Score quality, delivery, price and service after each quarter." />
            @else
                <div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Period</th><th class="num">Quality</th><th class="num">Delivery</th><th class="num">Price</th><th class="num">Service</th><th>Score</th><th class="hide-md">Comment</th></tr></thead><tbody>
                    @foreach ($s->scorecards->sortByDesc('period') as $k)<tr><td class="mono b">{{ $k->period }}</td><td class="num mono">{{ $k->quality }}</td><td class="num mono">{{ $k->delivery }}</td><td class="num mono">{{ $k->price }}</td><td class="num mono">{{ $k->service }}</td>
                        <td style="min-width:150px"><div class="row"><b class="mono">{{ number_format($k->score_tenths / 10, 1) }}</b><div class="prog {{ $k->score_tenths < 30 ? 'bad' : ($k->score_tenths < 35 ? 'warn' : 'ok') }}" style="flex:1"><i style="width:{{ $k->score_tenths * 2 }}%"></i></div></div></td><td class="hide-md muted">{{ $k->comment ?: '-' }}</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </x-card>
    @elseif ($tab === 'actions')
        <x-card title="Action plan" sub="What the supplier has agreed to improve" :flush="true">
            <x-slot:act>@if ($sourcing)@perm('suppliers', 'E')<button type="button" class="btn btn-sm" wire:click="open('act')"><x-icon name="plus" :size="14" />Add action</button>@endperm @endif</x-slot:act>
            @if ($s->actions->isEmpty())<x-empty icon="check" title="No actions" text="Add an action when a scorecard or an assessment shows something to fix." />
            @else<ul class="list">@foreach ($s->actions->sortBy([['status', 'desc'], ['due_on', 'asc']]) as $a)
                @php $late = $a->status === 'open' && $a->due_on->isPast() && ! $a->due_on->isToday(); @endphp
                <li wire:key="a-{{ $a->id }}"><div class="grow"><div class="t1">{{ $a->title }}</div><div class="muted sm">Due {{ $a->due_on->format('j M Y') }} · set by {{ $a->owner->name }}{{ $a->detail ? ' · ' . $a->detail : '' }}</div></div>
                    <x-badge :status="$a->status === 'done' ? 'ok' : ($late ? 'bad' : 'warn')" :label="$a->status === 'done' ? 'Done' : ($late ? 'Overdue' : 'Open')" />
                    @if ($sourcing && $a->status === 'open')@perm('suppliers', 'E')<button type="button" class="btn btn-sm" wire:click="closeAction({{ $a->id }})">Mark done</button>@endperm @endif</li>@endforeach</ul>@endif
        </x-card>
    @else
        <x-card><x-history :logs="$logs" /></x-card>
    @endif
    @if ($modal === 'doc')
        <x-modal title="Add a document"><div class="stack" style="gap:14px">
            <x-field label="Kind" for="dc-t"><select id="dc-t" class="select" wire:model="doc.type">@foreach (SupplierDocument::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-field>
            <x-field label="Title" for="dc-n"><input id="dc-n" class="input" wire:model="doc.title" placeholder="e.g. ISO 9001 certificate 2026"></x-field>
            <x-field label="Expires on" for="dc-e" :opt="true"><input id="dc-e" type="date" class="input" wire:model="doc.expires_on"></x-field>
            <x-field label="File" for="dc-f" :opt="true" name="file" help="PDF, JPG or PNG, up to 5 MB."><input id="dc-f" type="file" class="input" wire:model="file" accept=".pdf,.jpg,.jpeg,.png" style="padding:8px"></x-field>
            <div wire:loading wire:target="file" class="muted sm">Uploading the file...</div></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="addDocument" wire:loading.attr="disabled" wire:target="file,addDocument">Add document</button></x-slot:foot></x-modal>
    @elseif ($modal === 'card')
        <x-modal title="Scorecard"><div class="stack" style="gap:14px">
            <x-field label="Period" for="sc-p" help="Year and quarter, for example 2026-Q4. Saving the same period again replaces it."><input id="sc-p" class="input mono" wire:model="card.period"></x-field>
            <div class="fgrid">@foreach (['quality' => 'Quality', 'delivery' => 'Delivery', 'price' => 'Price', 'service' => 'Service'] as $k => $l)<x-field :label="$l" :for="'sc-' . $k"><select id="sc-{{ $k }}" class="select" wire:model="card.{{ $k }}">@foreach ([5 => '5 - excellent', 4 => '4 - good', 3 => '3 - acceptable', 2 => '2 - weak', 1 => '1 - poor'] as $v => $t)<option value="{{ $v }}">{{ $t }}</option>@endforeach</select></x-field>@endforeach</div>
            <x-field label="Comment" for="sc-c" :opt="true"><textarea id="sc-c" class="textarea" rows="2" wire:model="card.comment"></textarea></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="saveCard">Save scorecard</button></x-slot:foot></x-modal>
    @elseif ($modal === 'act')
        <x-modal title="Add an action"><div class="stack" style="gap:14px"><x-field label="What has to be done" for="ac-t"><input id="ac-t" class="input" wire:model="act.title" autofocus></x-field><x-field label="Due" for="ac-d"><input id="ac-d" type="date" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="act.due_on"></x-field><x-field label="Details" for="ac-x" :opt="true"><textarea id="ac-x" class="textarea" rows="2" wire:model="act.detail"></textarea></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="addAction">Add action</button></x-slot:foot></x-modal>
    @elseif ($modal === 'fa')
        <x-modal title="Request a field assessment"><div class="stack" style="gap:14px"><x-field label="Title" for="f2-t"><input id="f2-t" class="input" wire:model="fa.title"></x-field>
            <x-field label="Assign to" for="f2-a" :opt="true" help="Leave empty to let the supplier decide."><select id="f2-a" class="select" wire:model="fa.assignee"><option value="">Anyone at the supplier</option>@foreach ($workers as $w)<option value="{{ $w->id }}">{{ $w->name }} - {{ $w->roleLabel() }}</option>@endforeach</select></x-field>
            <x-field label="Due" for="f2-d" :opt="true"><input id="f2-d" type="date" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="fa.due_on"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="requestAssessment">Request assessment</button></x-slot:foot></x-modal>
    @endif
</div>
