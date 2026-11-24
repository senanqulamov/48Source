@php use App\Support\Money; $u = auth()->user(); $flow = ['draft', 'open', 'closed', 'awarded']; $at = array_search($rfq->status, $flow, true); @endphp
<div>
    <x-ph :title="$rfq->title" :crumbs="[[$u->isAdmin() ? 'RFQs' : 'My RFQs', route('rfqs.index')], [$rfq->number]]" :pager="$pager" :recent="$rfq->number" :recent-sub="$rfq->title">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $rfq->number }}</span><x-badge :status="$rfq->status" :label="$rfq->statusLabel()" /><span class="tag">{{ \App\Models\Rfq::TYPES[$rfq->type] }}</span>@if ($rfq->category)<span class="tag">{{ $rfq->category->name }}</span>@endif @if ($rfq->sealed)<span class="tag">Sealed bids</span>@endif</div></x-slot:pre>
        <a class="btn" href="{{ route('rfqs.pdf', $rfq) }}" target="_blank" rel="noopener"><x-icon name="download" />PDF</a>
        @if ($rfq->status === 'draft')
            @perm('rfqs', 'E')<a class="btn" href="{{ route('rfqs.edit', $rfq) }}" wire:navigate><x-icon name="edit" />Edit</a>@endperm
        @elseif ($rfq->status === 'open')
            @perm('rfqs', 'E')<button type="button" class="btn btn-p" wire:click="open('close')"><x-icon name="lock" />Close quoting</button>@endperm
        @endif
        @if (in_array($rfq->status, ['closed', 'awarded']) && $live->count() && $u->allowed('quotes'))
            <a class="btn {{ $rfq->status === 'closed' ? 'btn-p' : '' }}" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="cols" />{{ $rfq->status === 'closed' ? 'Compare and award' : 'View comparison' }}</a>
        @endif
        @if ($live->count() && $u->allowed('quotes'))
            <a class="btn" href="{{ route('assistant.index', ['request' => $rfq->number]) }}" wire:navigate><x-icon name="star" />Summarise</a>
        @endif
        @if (in_array($rfq->status, ['draft', 'open', 'closed']))@perm('rfqs', 'E')<button type="button" class="btn btn-d" wire:click="open('cancel')">Cancel request</button>@endperm @endif
        <span x-data="pop({ w: 240 })" style="display:contents">
            <button type="button" class="icon-btn" x-on:click="toggle($el)" aria-label="More actions" title="More actions" aria-haspopup="true"><x-icon name="more" /></button>
            <div class="pop" x-ref="p" x-show="open" x-cloak wire:ignore.self :style="pos" role="menu">
                @perm('rfqs', 'C')<button type="button" class="pop-i" wire:click="duplicate"><x-icon name="file" />Duplicate as draft</button>@endperm
                @if ($live->count() && ! $rfq->pricesHidden() && $u->allowed('quotes'))<a class="pop-i" href="{{ route('rfqs.scenarios', $rfq) }}" wire:navigate><x-icon name="cols" />Award scenarios</a>@endif
                @if ($u->isAdmin())
                    <hr><div class="pop-h">Administrator</div>
                    <button type="button" class="pop-i" wire:click="open('reassign')" x-on:click="open = false"><x-icon name="user" />Assign to another buyer</button>
                    @if ($rfq->status === 'closed')<button type="button" class="pop-i" wire:click="open('reopen')" x-on:click="open = false"><x-icon name="refresh" />Open again</button>
                    @if ($live->where('status', 'submitted')->count())<button type="button" class="pop-i" wire:click="open('adjust')" x-on:click="open = false"><x-icon name="sliders" />Adjust a quotation</button>@endif @endif
                @endif
            </div>
        </span>
    </x-ph>

    <x-facts :items="[
        ['Quotation deadline', $rfq->deadline_at?->format('j M Y') ?? 'Not set', $rfq->deadline_at && $rfq->status === 'open' ? '· ' . $rfq->deadline_at->diffForHumans(['parts' => 1]) : ''],
        ['Buyer', $rfq->buyer->name],
        ['Budget', $rfq->budget_cents ? Money::format($rfq->budget_cents, $rfq->currency) : 'Not set', '', true],
        ['Items', $rfq->items->count(), '', true],
        ['Quotations', $live->count() . ' of ' . $rfq->invitations->count(), 'invited', true],
    ]" />

    @if ($rfq->status === 'cancelled')
        <div style="margin-bottom:16px"><x-note kind="bad"><b>Cancelled</b> on {{ $rfq->cancelled_at->format('j M Y') }}. {{ $rfq->cancel_reason }}</x-note></div>
    @else
        @php $po = $rfq->purchaseOrders->first(); @endphp
        <x-life label="Request progress" :steps="['Draft', 'Open for quotes', 'Closed · comparing', 'Awarded']" :at="$at" :final="$rfq->status === 'awarded'">
            <x-slot:next>
                @switch($rfq->status)
                    @case('draft') {{ $blockers ? 'Before publishing: ' . $blockers[0] : 'Next step: publish this request so the invited suppliers can quote.' }} @break
                    @case('open') Now: waiting for quotations - {{ $live->count() }} of {{ $rfq->invitations->count() }} received. Quoting closes {{ $rfq->deadline_at->format('j M') }} ({{ $rfq->deadline_at->diffForHumans(['parts' => 1]) }}). @break
                    @case('closed') {{ $live->count() ? 'Next step: compare the ' . $live->count() . ' quotation' . ($live->count() === 1 ? '' : 's') . ' and accept one.' : 'No quotations arrived. Cancel it or create a new request.' }} @break
                    @case('awarded') Done: awarded to {{ $rfq->purchaseOrders->pluck('supplier.name')->join(', ', ' and ') }}. Follow delivery in the order. @break
                @endswitch
            </x-slot:next>
            @if ($rfq->status === 'open' && $live->count() < $rfq->invitations->count())@perm('rfqs', 'E')<button type="button" class="btn btn-sm" wire:click="remind" wire:loading.attr="disabled">Remind suppliers</button>@endperm @endif
            @if ($rfq->status === 'draft')@perm('rfqs', 'E')<button type="button" class="btn btn-sm btn-p" wire:click="publish" wire:loading.attr="disabled" @disabled((bool) $blockers)>Publish</button>@endperm
            @elseif ($rfq->status === 'closed' && $live->count() && $u->allowed('quotes'))<a class="btn btn-sm btn-p" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate>Compare quotations</a>
            @elseif ($rfq->status === 'awarded' && $po)<a class="btn btn-sm" href="{{ route('orders.show', $po) }}" wire:navigate>Open {{ $po->number }}</a>@endif
        </x-life>
    @endif

    <x-tabs :on="$tab" :tabs="['overview' => 'Overview', 'items' => ['Items', $rfq->items->count()], 'suppliers' => ['Suppliers', $rfq->invitations->count()], 'quotes' => ['Quotations', $live->count()], 'qa' => ['Questions', $qa->count()], 'files' => ['Files', $files->count()], 'activity' => 'Activity']" />

    @if ($tab === 'overview')
        @php
            $mine = $u->isAdmin() || $rfq->buyer_id === $u->id;
            $awardBy = $rfq->status === 'closed' ? ($rfq->closed_at ?? $rfq->deadline_at)?->copy()->addDays(\App\Support\Setting::int('sla.award_days')) : ($rfq->deadline_at?->copy()->addDays(\App\Support\Setting::int('sla.award_days')));
            $canSeal = $mine && $u->allowed('rfqs', 'E') && ($rfq->status === 'draft' || ($rfq->status === 'open' && ! $rfq->sealed && $live->isEmpty()));
        @endphp
        <div class="grid g-2-1">
            <div class="stack">
                @if ($assessments->isNotEmpty() || ($u->allowed('field', 'A') && in_array($rfq->status, ['open', 'closed']) && $rfq->invitations->isNotEmpty()))
                    <x-card title="Field assessments" :sub="$assessments->isEmpty() ? 'Site checks asked from invited suppliers' : 'Open one to approve or reject it'" :flush="true">
                        <x-slot:act>@if ($u->allowed('field', 'A') && in_array($rfq->status, ['open', 'closed']) && $rfq->invitations->isNotEmpty())<button type="button" class="btn btn-sm" wire:click="open('fa')"><x-icon name="plus" :size="14" />Request</button>@endif</x-slot:act>
                        @if ($assessments->isEmpty())<div class="card-b faint">None requested yet.</div>
                        @else<ul class="list">@foreach ($assessments as $a)<li class="click" style="cursor:pointer" data-href="{{ route('assessments.show', $a) }}" tabindex="0"><div class="grow"><div class="t1">{{ $a->title }}</div><div class="muted sm"><span class="mono">{{ $a->number }}</span> · {{ $a->supplier->name }}</div></div><x-badge :status="['submitted' => 'info', 'approved' => 'ok', 'rejected' => 'bad', 'in_progress' => 'warn'][$a->status] ?? 'draft'" :label="$a->statusLabel()" /><x-icon name="right" /></li>@endforeach</ul>@endif
                    </x-card>
                @endif
                @if ($u->isAdmin())
                    <x-card title="Admin controls" sub="Only administrators see this">
                        <div class="fgrid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                            <x-field label="Change status" for="ac1" help="Awarded is set by accepting a quotation.">
                                <select id="ac1" class="select" wire:change="adminStatus($event.target.value)" wire:key="ac1-{{ $rfq->status }}-{{ $modal }}" @disabled(in_array($rfq->status, ['awarded', 'cancelled']))>
                                    @foreach (\App\Models\Rfq::STATUSES as $k => $label)@if ($k !== 'awarded' || $rfq->status === 'awarded')<option value="{{ $k }}" @selected($rfq->status === $k)>{{ $label }}</option>@endif @endforeach
                                </select>
                            </x-field>
                            <x-field label="Request type" for="ac2">
                                <select id="ac2" class="select" wire:change="adminType($event.target.value)" wire:key="ac2-{{ $rfq->type }}" @disabled(! in_array($rfq->status, ['draft', 'open']))>
                                    @foreach (\App\Models\Rfq::TYPES as $k => $label)<option value="{{ $k }}" @selected($rfq->type === $k)>{{ $label }}</option>@endforeach
                                </select>
                            </x-field>
                            <x-field label="Buyer" for="ac3"><button id="ac3" type="button" class="btn" style="justify-content:flex-start;width:100%" wire:click="open('reassign')"><x-icon name="user" />{{ $rfq->buyer->name }}</button></x-field>
                            <div class="field"><span class="lbl">Field assessment</span><label class="row" style="height:34px;cursor:pointer"><input type="checkbox" class="chk" wire:change="adminAssessment($event.target.checked)" @checked($rfq->requires_assessment) @disabled(in_array($rfq->status, ['awarded', 'cancelled']))>Requires field assessment</label></div>
                        </div>
                    </x-card>
                @endif
                <x-card title="Details"><dl class="kv">
                    <dt>Delivery location</dt><dd>{{ $rfq->delivery_location ?: '-' }}</dd>
                    <dt>Payment terms</dt><dd>{{ $rfq->payment_terms ?: '-' }}</dd>
                    <dt>Currency</dt><dd>{{ $rfq->currency }}</dd>
                    <dt>Budget</dt><dd class="mono">{{ Money::format($rfq->budget_cents, $rfq->currency) }}</dd>
                    <dt>Scoring</dt><dd>{{ \App\Services\QuoteEvaluator::PRESETS[$rfq->scoring_preset]['label'] ?? $rfq->scoring_preset }}</dd>
                    <dt>Bids</dt><dd>{{ $rfq->sealed ? 'Sealed until the deadline' : 'Visible as they arrive' }}</dd>
                </dl></x-card>
                @if ($rfq->description)<x-card title="Description"><p style="white-space:pre-line">{{ $rfq->description }}</p>@if ($rfq->requirements)<h3 class="lbl" style="margin:14px 0 6px">Requirements</h3><p style="white-space:pre-line">{{ $rfq->requirements }}</p>@endif</x-card>
                @elseif ($rfq->requirements)<x-card title="Requirements for suppliers"><p style="white-space:pre-line">{{ $rfq->requirements }}</p></x-card>@endif
                @if ($rfq->status !== 'cancelled')
                    <x-card title="Sourcing tools" sub="Clarify, negotiate and compare before you award">
                        <div class="row wrap" style="gap:8px">
                            <button type="button" class="btn" wire:click="$set('tab', 'qa')"><x-icon name="mail" />Questions &amp; answers @if ($openQuestions)<span class="badge b-warn plain">{{ $openQuestions }} open</span>@endif</button>
                            @if ($rfq->auction)<a class="btn" href="{{ route('rfqs.auction', $rfq) }}" wire:navigate><x-icon name="pulse" />{{ $rfq->auction->status === 'live' ? 'Live auction' : 'Auction result' }}</a>
                            @elseif (in_array($rfq->status, ['open', 'closed']) && $live->count() >= 2 && ! $rfq->pricesHidden() && $u->allowed('quotes'))<a class="btn" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="pulse" />Start reverse auction</a>@endif
                            @if ($rfq->status === 'closed' && $live->count() && $u->allowed('quotes'))<a class="btn" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="refresh" />Ask for best and final offers</a>@endif
                            @if ($live->count() >= 2 && ! $rfq->pricesHidden() && $u->allowed('quotes'))<a class="btn" href="{{ route('rfqs.scenarios', $rfq) }}" wire:navigate><x-icon name="cols" />Award scenarios</a>@endif
                            @if ($canSeal || ($rfq->sealed && in_array($rfq->status, ['draft', 'open'])))<button type="button" class="btn" wire:click="toggleSeal" @disabled(! $canSeal)><x-icon name="lock" />{{ $rfq->sealed ? 'Sealed bids: on' : 'Seal bids until deadline' }}</button>@endif
                            @if ($live->count() && $u->allowed('quotes'))<a class="btn" href="{{ route('assistant.index', ['request' => $rfq->number]) }}" wire:navigate><x-icon name="star" />Summarise quotations</a>@endif
                        </div>
                        @if ($rfq->round > 1)<p class="help" style="margin-top:10px">Round {{ $rfq->round }} - best and final offers.</p>@endif
                    </x-card>
                @endif
            </div>
            <div class="stack">
                <x-card title="Important dates"><dl class="kv" style="grid-template-columns:150px 1fr">
                    <dt>Created</dt><dd>{{ $rfq->created_at->format('j M Y') }}</dd>
                    <dt>Published</dt><dd>{{ $rfq->published_at?->format('j M Y') ?? '-' }}</dd>
                    <dt>Quotation deadline</dt><dd>{{ $rfq->deadline_at?->format('j M Y, H:i') ?? '-' }}</dd>
                    @if (in_array($rfq->status, ['open', 'closed']))<dt>Award target</dt><dd>{{ $awardBy?->format('j M Y') ?? '-' }}</dd>@endif
                    @if ($rfq->awarded_at)<dt>Awarded</dt><dd>{{ $rfq->awarded_at->format('j M Y') }}</dd>@endif
                    <dt>Delivery required</dt><dd>{{ $rfq->required_by?->format('j M Y') ?? '-' }}</dd>
                </dl></x-card>
                <x-card title="Buyer">
                    <div class="row" style="gap:12px;align-items:flex-start">
                        <span class="avatar" aria-hidden="true" style="width:44px;height:44px;font-size:15px">{{ $rfq->buyer->initials() }}</span>
                        <div style="min-width:0"><div class="b">{{ $rfq->buyer->name }}</div><div class="muted sm">{{ $rfq->buyer->roleLabel() }}@if ($rfq->buyer->department) · {{ $rfq->buyer->department }}@endif</div><div class="sm trunc">{{ $rfq->buyer->email }}</div></div>
                    </div>
                </x-card>
                <x-card title="Latest activity" :flush="true">
                    <x-slot:act><button type="button" class="link" wire:click="$set('tab', 'activity')">View all</button></x-slot:act>
                    @if ($latest->isEmpty())<div class="card-b faint">Nothing recorded yet.</div>
                    @else<ul class="list">@foreach ($latest as $log)<li data-plain><div class="grow"><div>{{ $log->action }}</div><div class="muted sm">{{ $log->actor_name }} · {{ $log->created_at->format('j M Y') }}</div></div></li>@endforeach</ul>@endif
                </x-card>
            </div>
        </div>
    @elseif ($tab === 'items')
        @php $est = $rfq->items->sum(fn ($i) => $i->target_price_cents ? Money::lineTotal($i->target_price_cents, $i->quantity_milli) : 0); $priced = $rfq->items->whereNotNull('target_price_cents')->count(); @endphp
        <x-card title="Requested items" :sub="$rfq->budget_cents ? 'Budget ' . Money::format($rfq->budget_cents, $rfq->currency, false) : 'No budget set'" :flush="true">
            <x-slot:act><button type="button" class="btn btn-sm" wire:click="exportItems('xlsx')"><x-icon name="download" :size="14" />Excel</button></x-slot:act>
            <div class="tbl-wrap"><table class="tbl"><thead><tr><th style="width:44px">#</th><th>Item</th><th class="num">Quantity</th><th>Unit</th><th class="num">Target unit price</th><th class="num">Estimated total</th></tr></thead><tbody>
                @foreach ($rfq->items as $i)<tr data-plain><td class="mono muted">{{ $i->position }}</td><td class="t1">{{ $i->name }}</td><td class="num mono">{{ $i->qty() }}</td><td>{{ $i->unit }}</td><td class="num mono">{{ $i->target_price_cents ? Money::format($i->target_price_cents, $rfq->currency) : '-' }}</td><td class="num mono">{{ $i->target_price_cents ? Money::format(Money::lineTotal($i->target_price_cents, $i->quantity_milli), $rfq->currency) : '-' }}</td></tr>@endforeach
                <tr data-plain><td></td><td class="b">Estimated value</td><td></td><td></td><td class="num muted sm">@if ($priced < $rfq->items->count()){{ $priced }} of {{ $rfq->items->count() }} items have a target price @endif</td><td class="num mono b">{{ $priced ? Money::format($est, $rfq->currency) : '-' }}</td></tr>
            </tbody></table></div>
        </x-card>
    @elseif ($tab === 'suppliers')
        @php $quotedIds = $live->pluck('supplier_id')->all(); @endphp
        <x-card title="Supplier invitations" :sub="count(array_intersect($quotedIds, $rfq->invitations->pluck('supplier_id')->all())) . ' of ' . $rfq->invitations->count() . ' have quoted'" :flush="true">
            <x-slot:act>@if ($canInvite)<button type="button" class="btn btn-sm" wire:click="open('invite')"><x-icon name="plus" :size="14" />Invite supplier</button>@endif</x-slot:act>
            @if ($rfq->invitations->isEmpty())<x-empty icon="building" title="No suppliers invited" :text="$rfq->type === 'public' ? 'This is a public tender. Suppliers appear here when they join.' : 'Invite the suppliers that should quote on this request.'" />
            @else<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Supplier</th><th class="hide-sm">Contact</th><th>Invitation</th><th class="hide-sm">Rating</th><th class="hide-md">On-time</th><th></th></tr></thead><tbody>
                @foreach ($rfq->invitations as $inv)
                    @php $q = $quotes->firstWhere('supplier_id', $inv->supplier_id); $quoted = $q && in_array($q->status, ['submitted', 'won', 'lost'], true); $sp = $inv->supplier; @endphp
                    <tr wire:key="inv-{{ $inv->id }}">
                        <td><a class="t1" href="{{ route('suppliers.show', $sp->id) }}" wire:navigate>{{ $sp->name }}</a><div class="t2">{{ $sp->city ?: '-' }}</div></td>
                        <td class="hide-sm">{{ $sp->contact_name ?: '-' }}<div class="t2">{{ $sp->email }}</div></td>
                        <td>@if ($quoted)<x-badge status="ok" label="Quoted" />@elseif ($q && $q->status === 'withdrawn')<x-badge status="warn" label="Withdrawn" />@else<x-badge :status="$inv->status" />@endif @if ($inv->decline_reason)<div class="t2">{{ $inv->decline_reason }}</div>@endif</td>
                        <td class="hide-sm"><span class="row" style="gap:5px"><x-icon name="star" :size="14" style="color:#F2A900" /><b class="mono">{{ number_format($sp->rating_tenths / 10, 1) }}</b></span></td>
                        <td class="hide-md mono">{{ isset($onTime[$sp->id]) ? $onTime[$sp->id] . '%' : '-' }}</td>
                        <td class="num">@if ($rfq->status === 'open' && ! $quoted && $inv->status !== 'declined')@perm('rfqs', 'E')<button type="button" class="btn btn-sm" wire:click.stop="remindOne({{ $sp->id }})" wire:loading.attr="disabled" wire:target="remindOne({{ $sp->id }})"><x-icon name="send" :size="14" />Remind</button>@endperm @endif</td>
                    </tr>
                @endforeach
            </tbody></table></div>@endif
        </x-card>
    @elseif ($tab === 'quotes')
        @if ($rfq->pricesHidden())
            <x-card><x-empty icon="lock" title="Sealed until the deadline" :text="$live->count() . ' quotation(s) have arrived. Supplier names and prices open for everyone at once when quoting closes.'" /></x-card>
        @elseif ($quotes->isEmpty())
            <x-card><x-empty icon="inbox" title="No quotations yet" :text="$rfq->status === 'open' ? 'Suppliers have until ' . $rfq->deadline_at->format('j M Y, H:i') . '.' : 'No supplier sent a quotation.'" /></x-card>
        @else
            @php $full = $live->filter(fn ($q) => $q->coverage() === $rfq->items->count()); $lowest = $full->min('total_cents'); @endphp
            <div class="row wrap" style="margin-bottom:12px"><span class="muted">{{ $quotes->count() }} quotation{{ $quotes->count() === 1 ? '' : 's' }} · open one to see every line{{ $u->isAdmin() ? ' and adjust prices' : '' }}.</span><span class="sp"></span>
                @if ($live->count() && $u->allowed('quotes'))<a class="btn btn-p" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="cols" />Compare side by side</a>@endif</div>
            <div class="stack" style="gap:10px">
                @foreach ($quotes as $n => $q)
                    @php $lines = $q->items->keyBy('rfq_item_id'); @endphp
                    <details class="qd" @if ($loop->first) open @endif wire:key="q-{{ $q->id }}"><summary>
                        <span style="flex:1;min-width:160px"><b>{{ $q->supplier->name }}</b>@if ($q->status !== 'withdrawn' && $lowest !== null && $q->total_cents === $lowest && $q->coverage() === $rfq->items->count()) <span class="badge b-ok plain">Lowest price</span>@endif @if ($q->coverage() < $rfq->items->count()) <span class="badge b-warn plain">Partial · {{ $q->coverage() }}/{{ $rfq->items->count() }}</span>@endif
                            <span class="muted sm" style="display:block">Submitted {{ $q->submitted_at?->format('j M Y, H:i') }} · revision {{ $q->revision }} · {{ $q->delivery_days }} days delivery · {{ $q->payment_terms ?: 'no terms stated' }}</span></span>
                        <span class="num"><span class="muted xs" style="display:block">Total excl. tax</span><b class="mono" style="font-size:15px">{{ Money::format($q->total_cents, $q->currency) }}</b></span>
                        <x-badge :status="$q->status" :label="$q->status === 'submitted' && $rfq->status === 'closed' ? 'Under review' : $q->statusLabel()" />
                        @if ($rfq->status === 'closed' && $q->status === 'submitted' && $q->coverage() === $rfq->items->count() && $u->allowed('quotes', 'A') && ($u->isAdmin() || $rfq->buyer_id === $u->id))<a class="btn btn-sm btn-p" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="check" :size="14" />Accept</a>@endif
                        <span class="cv"><x-icon name="down" /></span></summary>
                        @if ($u->isAdmin() && $q->status === 'submitted' && $rfq->status === 'closed')
                            <div class="adjtool" wire:key="adj-{{ $q->id }}">
                                <div class="row wrap"><b><x-icon name="sliders" :size="14" /> Price adjustment tool</b><span class="tag">Admin only</span></div>
                                <div class="row wrap" style="margin-top:8px;align-items:flex-end">
                                    <div class="field" style="flex:0 1 220px"><label for="adj-t-{{ $q->id }}">Target total ({{ $q->currency }})</label><input id="adj-t-{{ $q->id }}" class="input mono" inputmode="decimal" placeholder="{{ Money::input((int) round($q->total_cents * 0.97)) }}" wire:model="adj.{{ $q->id }}.target"></div>
                                    <div class="field" style="flex:1 1 240px"><label for="adj-r-{{ $q->id }}">Reason (recorded in the audit trail)</label><input id="adj-r-{{ $q->id }}" class="input" placeholder="e.g. Negotiated discount confirmed by email on 20 Nov" wire:model="adj.{{ $q->id }}.reason"></div>
                                    <button type="button" class="btn" wire:click="adjustQuote({{ $q->id }})" wire:loading.attr="disabled" wire:target="adjustQuote({{ $q->id }})">Apply adjusted prices</button>
                                </div>
                                <p class="help" style="margin-top:6px">Records a total agreed with the supplier outside the system. Every line price is reduced in the same proportion, the original total is kept and shown to the buyer, and the supplier is told.</p>
                            </div>
                        @endif
                        <div class="tbl-wrap"><table class="tbl compact"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Total</th></tr></thead><tbody>
                            @foreach ($rfq->items as $it)
                                @php $l = $lines[$it->id] ?? null; if ($l && $l->unit_price_cents === null) { $l = null; } @endphp
                                <tr><td class="t1">{{ $it->name }}</td><td class="num mono">{{ $it->qty() }} <span class="faint">{{ $it->unit }}</span></td>
                                    <td class="num mono">@if ($l){{ Money::format($l->unit_price_cents, $q->currency) }}@else<span class="faint">Not quoted</span>@endif</td><td class="num mono">{{ $l ? Money::format($l->total_cents, $q->currency) : '-' }}</td></tr>
                            @endforeach
                            <tr><td class="b">Total excl. tax</td><td></td><td></td><td class="num mono b">{{ Money::format($q->total_cents, $q->currency) }}</td></tr>
                        </tbody></table></div>
                        <div class="row wrap sm muted" style="padding:10px 16px;border-top:1px solid var(--line);gap:18px"><span>Valid until <b style="color:var(--text)">{{ $q->valid_until?->format('j M Y') ?? 'not stated' }}</b>@if ($q->isExpired()) <x-badge status="bad" label="Expired" />@endif</span>
                            @if ($rfq->budget_cents && $q->coverage() === $rfq->items->count())<span>{{ $q->total_cents > $rfq->budget_cents ? 'Over' : 'Under' }} budget by <b class="mono" style="color:var(--text)">{{ Money::format(abs($q->total_cents - $rfq->budget_cents), $q->currency) }}</b></span>@endif
                            @php $fa = $faBySupplier[$q->supplier_id] ?? null; @endphp
                            @if ($fa || $rfq->requires_assessment)<span>Field assessment @if ($fa)<x-badge :status="['submitted' => 'info', 'approved' => 'ok', 'rejected' => 'bad', 'in_progress' => 'warn'][$fa->status] ?? 'draft'" :label="$fa->statusLabel()" />@else<x-badge status="warn" label="Required, not requested" />@endif</span>@endif
                            @if ($q->notes)<span>Note: {{ $q->notes }}</span>@endif
                            @foreach ($q->attachments as $f)<a class="link" href="{{ route('files.show', $f->id) }}"><x-icon name="paperclip" :size="13" /> {{ $f->name }}</a>@endforeach</div>
                        @if ($q->original_total_cents)<div style="padding:0 16px 12px"><x-note kind="warn"><b>Total adjusted</b> by an administrator on {{ $q->adjusted_at->format('j M Y') }} from {{ Money::format($q->original_total_cents, $q->currency) }} to {{ Money::format($q->total_cents, $q->currency) }}. Reason: {{ $q->adjust_reason }}</x-note></div>@endif
                    </details>
                @endforeach
            </div>
        @endif
    @elseif ($tab === 'qa')
        <x-card title="Questions from suppliers" sub="Your answers are shown to every invited supplier, without saying who asked" :flush="true">
            @if ($qa->isEmpty())<x-empty icon="inbox" title="No questions yet" text="Suppliers ask on their quotation page while the request is open. You answer here, and every invited supplier sees the answer." />
            @else
                <ul class="list">
                    @foreach ($qa as $q)
                        <li style="display:block" wire:key="qa-{{ $q->id }}"><div class="row"><div class="grow"><div class="t1">{{ $q->question }}</div><div class="muted sm">{{ $q->supplier->name }} · {{ $q->created_at->format('j M Y, H:i') }}</div></div><x-badge :status="$q->answer ? 'ok' : 'warn'" :label="$q->answer ? 'Answered' : 'Waiting'" /></div>
                            @if ($q->answer)<div style="margin-top:8px;padding:10px 12px;background:var(--surface-2);border-radius:10px">{{ $q->answer }}</div>
                            @else @perm('rfqs', 'E')<div class="row" style="margin-top:8px;align-items:flex-end"><div class="field grow"><label for="rp-{{ $q->id }}" class="sr">Answer</label><textarea id="rp-{{ $q->id }}" class="textarea" rows="2" wire:model="reply.{{ $q->id }}" placeholder="Write the answer"></textarea></div><button type="button" class="btn btn-p" wire:click="answer({{ $q->id }})">Answer</button></div>@endperm @endif</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @elseif ($tab === 'files')
        @php $mayFile = $u->allowed('rfqs', 'E') && ($u->isAdmin() || $rfq->buyer_id === $u->id); $canFile = $mayFile && in_array($rfq->status, ['draft', 'open']); @endphp
        <x-card title="Related files" sub="Specifications, drawings and terms. Every invited supplier can download them." :flush="true">
            <x-slot:act>
                @if ($canFile)<label class="btn btn-sm" style="cursor:pointer" wire:loading.class="busy" wire:target="upload"><input type="file" wire:model="upload" style="position:absolute;opacity:0;width:1px;height:1px" aria-label="Upload a file"><x-icon name="upload" :size="14" /><span wire:loading.remove wire:target="upload">Upload</span><span wire:loading wire:target="upload">Uploading...</span></label>
                @elseif ($mayFile)<button type="button" class="btn btn-sm" disabled data-tip="Files can be added while the request is a draft or open."><x-icon name="upload" :size="14" />Upload</button>@endif
            </x-slot:act>
            @error('upload')<div class="card-b" style="border-bottom:1px solid var(--line)"><p class="err">{{ $message }}</p></div>@enderror
            @if ($canFile && $files->isEmpty())<div class="card-b" style="border-bottom:1px solid var(--line)"><label class="drop" style="display:block;cursor:pointer"><input type="file" wire:model="upload" style="position:absolute;opacity:0;width:1px;height:1px" aria-label="Add a file"><x-icon name="upload" :size="20" /><div class="b" style="margin-top:6px">Choose a file to add</div><div class="muted sm">PDF, Office, image, CSV, DWG or ZIP, up to 10 MB</div></label></div>@endif
            @if ($files->isEmpty())<x-empty icon="paperclip" title="No files yet" :text="$canFile ? 'Add the specification or drawings suppliers need to price the request.' : 'Nothing was attached to this request.'" />
            @else<ul class="list">@foreach ($files as $f)<li wire:key="f-{{ $f->id }}"><x-icon name="paperclip" /><div class="grow" style="min-width:0"><div class="t1 trunc">{{ $f->name }}</div><div class="muted sm">{{ $f->sizeLabel() }} · {{ $f->uploader->name }} · {{ $f->created_at->format('j M Y, H:i') }}</div></div><a class="btn btn-sm btn-g" href="{{ route('files.show', $f->id) }}"><x-icon name="download" :size="14" />Download</a>@if ($canFile)<button type="button" class="icon-btn" wire:click="removeFile({{ $f->id }})" wire:confirm="Remove {{ $f->name }}?" aria-label="Remove {{ $f->name }}"><x-icon name="trash" :size="15" /></button>@endif</li>@endforeach</ul>@endif
        </x-card>
    @else
        <x-card><x-history :logs="$logs" /></x-card>
    @endif

    @if ($modal === 'close')
        <x-modal title="Close quoting?">
            <p>Suppliers can no longer send or change quotations on <b class="mono">{{ $rfq->number }}</b>. {{ $live->count() }} quotation(s) have arrived. You can then compare and award.</p>
            <x-slot:foot><button type="button" class="btn" wire:click="close">Keep it open</button><button type="button" class="btn btn-p" wire:click="closeQuoting">Close quoting</button></x-slot:foot>
        </x-modal>
    @elseif ($modal === 'cancel')
        <x-modal title="Cancel this request?">
            <p style="margin-bottom:12px">Invited suppliers are told that <b class="mono">{{ $rfq->number }}</b> was cancelled. This cannot be undone.</p>
            <x-field label="Reason" for="reason" help="Recorded in the activity log."><textarea id="reason" class="textarea" rows="3" wire:model="reason" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" wire:click="close">Keep the request</button><button type="button" class="btn btn-d solid" wire:click="cancel">Cancel request</button></x-slot:foot>
        </x-modal>
    @endif
    @if ($modal === 'fa')
        <x-modal title="Request a field assessment">
            <div class="stack" style="gap:14px">
                <x-field label="Supplier" for="fa-s"><select id="fa-s" class="select" wire:model="fa.supplier_id"><option value="">Choose an invited supplier</option>@foreach ($rfq->invitations as $inv)<option value="{{ $inv->supplier_id }}">{{ $inv->supplier->name }}</option>@endforeach</select></x-field>
                <x-field label="Title" for="fa-t"><input id="fa-t" class="input" wire:model="fa.title"></x-field>
                <x-field label="Due" for="fa-d" :opt="true"><input id="fa-d" type="date" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="fa.due_on"></x-field>
                <p class="help">The supplier's field worker answers a five-point site checklist and writes the findings. You then approve it or return it.</p>
            </div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="requestAssessment">Request assessment</button></x-slot:foot>
        </x-modal>
    @endif
    @if ($modal === 'invite')
        <x-modal title="Invite a supplier">
            <x-field label="Supplier" for="iv-s" help="Only active suppliers that are not invited yet are listed."><select id="iv-s" class="select" wire:model="inviteId"><option value="">Choose a supplier</option>@foreach ($inviteable as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></x-field>
            @if ($rfq->status === 'open')<p class="help" style="margin-top:10px">The request is open, so the supplier is told at once and can quote until {{ $rfq->deadline_at->format('j M Y, H:i') }}.</p>@endif
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="invite">Invite</button></x-slot:foot>
        </x-modal>
    @endif
    @if ($modal === 'reopen')
        <x-modal title="Open this request again?"><p class="muted" style="margin-bottom:12px">Suppliers can send and revise quotations until the new deadline. Use this only when the request closed too early.</p>
            <div class="stack" style="gap:14px"><x-field label="New deadline" for="ro-d"><input id="ro-d" type="datetime-local" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="tool.deadline"></x-field><x-field label="Reason" for="ro-r" help="Stored in the activity log."><textarea id="ro-r" class="textarea" rows="2" wire:model="tool.reason"></textarea></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="reopen">Open again</button></x-slot:foot></x-modal>
    @elseif ($modal === 'reassign')
        <x-modal title="Assign to another buyer"><x-field label="Buyer" for="ra-b" :help="'Now owned by ' . $rfq->buyer->name . '.'"><select id="ra-b" class="select" wire:model="tool.buyer"><option value="">Choose a buyer</option>@foreach ($buyers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="reassign">Assign</button></x-slot:foot></x-modal>
    @elseif ($modal === 'adjust')
        <x-modal title="Adjust a quotation total"><p class="muted" style="margin-bottom:12px">Record a total agreed with the supplier outside the system. Line prices are reduced in proportion, the original total is kept and shown, and the supplier is told.</p>
            <div class="stack" style="gap:14px"><x-field label="Quotation" for="aj-q"><select id="aj-q" class="select" wire:model="tool.quote"><option value="">Choose a quotation</option>@foreach ($live->where('status', 'submitted') as $q)<option value="{{ $q->id }}">{{ $q->supplier->name }} - {{ Money::format($q->total_cents, $q->currency) }}</option>@endforeach</select></x-field>
                <x-field label="Agreed total" for="aj-t"><input id="aj-t" class="input mono" inputmode="decimal" wire:model="tool.target" placeholder="0.00"></x-field><x-field label="Reason" for="aj-r" help="For example: discount confirmed by email on 20 Nov. Stored in the activity log."><textarea id="aj-r" class="textarea" rows="2" wire:model="tool.reason"></textarea></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="adjust">Adjust total</button></x-slot:foot></x-modal>
    @endif
</div>
