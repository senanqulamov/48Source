@php use App\Support\Money; $canAward = auth()->user()->allowed('quotes', 'A') && $rfq->status === 'closed' && ! $blockers; $n = $rfq->items->count(); @endphp
<div>
    <x-ph title="Compare quotations" :crumbs="[[auth()->user()->isAdmin() ? 'RFQs' : 'My RFQs', route('rfqs.index')], [$rfq->number, route('rfqs.show', $rfq)], ['Compare']]" :recent="'Compare ' . $rfq->number" :recent-sub="$rfq->title">
        <x-slot:pre></x-slot:pre>
        @if ($rfq->status === 'closed' && auth()->user()->allowed('quotes', 'A'))
            @if ($round)<button type="button" class="btn" wire:click="closeBafo" wire:confirm="Close the best-and-final round now?">Close round {{ $round->round }}</button>
            @elseif (! $auction || $auction->status !== 'live')<button type="button" class="btn" wire:click="open('bafo')"><x-icon name="refresh" />Best and final</button>@endif
            @if (! $auction && ! $round && $rows->count() > 1)<button type="button" class="btn" wire:click="open('auction')"><x-icon name="pulse" />Reverse auction</button>@endif
        @endif
        @if ($auction)<a class="btn" href="{{ route('rfqs.auction', $rfq) }}" wire:navigate><x-icon name="pulse" />{{ $auction->status === 'live' ? 'Watch auction' : 'Auction result' }}</a>@endif
        @if ($rows->count() > 1)<a class="btn" href="{{ route('rfqs.scenarios', $rfq) }}" wire:navigate><x-icon name="chart" />Scenarios</a>@endif
        <a class="btn" href="{{ route('rfqs.show', $rfq) }}" wire:navigate><x-icon name="file" />Open RFQ</a>
    </x-ph>
    <p class="ph-d" style="margin:-10px 0 16px"><span class="mono">{{ $rfq->number }}</span> · {{ $rfq->title }}</p>

    @if ($round)
        <div style="margin-bottom:16px"><x-note kind="info"><b>Best-and-final round {{ $round->round }} is open</b> until {{ $round->deadline_at->format('j M Y, H:i') }}. Suppliers may still lower their prices; awarding waits until it ends.</x-note></div>
    @elseif ($rounds)
        <div style="margin-bottom:16px"><x-note kind="ok">{{ $rounds }} best-and-final round{{ $rounds === 1 ? '' : 's' }} held. The prices below are the final ones.</x-note></div>
    @endif
    @if ($rfq->status === 'awarded')
        <div style="margin-bottom:16px"><x-note kind="ok"><b>This request is awarded.</b> The comparison is shown as it stood.</x-note></div>
    @elseif ($blockers)
        <div style="margin-bottom:16px"><x-note kind="warn"><b>Not ready to award:</b> {{ implode(' ', $blockers) }}</x-note></div>
    @endif

    @if ($rows->isEmpty())
        <x-card><x-empty icon="inbox" title="No quotations to compare" text="Quotations appear here once suppliers submit them."><a class="btn btn-p" href="{{ route('rfqs.show', $rfq) }}" wire:navigate>Back to the request</a></x-empty></x-card>
    @else
        @php
            $best = $rows->firstWhere('rank', 1); $fast = $rows->sortBy(fn ($r) => $r['quote']->delivery_days)->first();
            $cur = fn ($r) => Money::format($r['quote']->total_cents, $r['quote']->currency);
        @endphp
        <div class="verdicts">
            <div class="verdict best"><h4>Best overall value</h4><b>{{ $best['quote']->supplier->name }}</b><div class="muted sm">Score {{ round($best['score']) }} · {{ $cur($best) }}.{{ $lowest && $lowest->id === $best['quote']->id ? ' Also the lowest full quotation.' : '' }}</div></div>
            <div class="verdict"><h4>Lowest price</h4>@if ($lowest)<b>{{ $lowest->supplier->name }}</b><div class="muted sm">{{ Money::format($lowest->total_cents, $lowest->currency) }} · {{ $lowest->delivery_days }}-day delivery.</div>@else<b>None yet</b><div class="muted sm">No quotation prices every item.</div>@endif</div>
            <div class="verdict"><h4>Fastest delivery</h4><b>{{ $fast['quote']->supplier->name }}</b><div class="muted sm">{{ $fast['quote']->delivery_days }} days from order.</div></div>
        </div>
        <section class="card">
            <div class="toolbar"><span class="lbl">{{ $rows->count() }} supplier{{ $rows->count() === 1 ? '' : 's' }} · {{ $n }} item{{ $n === 1 ? '' : 's' }}</span><span class="sp"></span>
                <span class="row"><span class="lbl hide-sm">Weighting</span><div class="seg" role="group" aria-label="Weighting">
                    @foreach (\App\Services\QuoteEvaluator::PRESETS as $k => $p)<button type="button" class="{{ $preset === $k ? 'on' : '' }}" aria-pressed="{{ $preset === $k ? 'true' : 'false' }}" wire:click="$set('preset', '{{ $k }}')" title="Price {{ $p['price'] }}%, quality {{ $p['quality'] }}%, delivery {{ $p['delivery'] }}%, terms {{ $p['terms'] }}%">{{ $p['label'] }}</button>@endforeach
                </div></span></div>
            <div class="cmp-wrap" aria-label="Quotation comparison table"><table class="cmp"><thead><tr>
                <th class="rh"><div class="b">{{ $n }} item{{ $n === 1 ? '' : 's' }} · {{ $rows->count() }} supplier{{ $rows->count() === 1 ? '' : 's' }}</div><div class="t2">Weighting: {{ mb_strtolower(\App\Services\QuoteEvaluator::PRESETS[$preset]['label']) }}</div></th>
                @foreach ($rows as $r)
                    @php $q = $r['quote']; @endphp
                    <th scope="col" class="{{ $r['rank'] === 1 ? 'best' : '' }} {{ $pick === $q->id ? 'picked' : '' }}">
                        <div class="row wrap" style="gap:6px;margin-bottom:4px">@if ($r['rank'] === 1)<span class="badge b-info plain">Best value</span>@endif @if ($lowest && $lowest->id === $q->id)<span class="badge b-ok plain">Lowest price</span>@endif @if (in_array($q->status, ['won', 'lost']))<x-badge :status="$q->status" :label="$q->statusLabel()" class="plain" />@endif @unless ($r['complete'])<span class="badge b-warn plain">Partial · {{ $r['coverage'] }}/{{ $n }}</span>@endunless</div>
                        <span class="sname">{{ $q->supplier->name }}</span><div class="t2 muted sm">{{ $q->supplier->city ?? 'No city' }} · quoted {{ $q->submitted_at?->format('j M') }}</div>
                        <div class="score"><b>{{ round($r['score']) }}</b><div class="prog {{ $r['score'] < 90 ? 'warn' : '' }}"><i style="width:{{ min(100, round($r['score'], 1)) }}%"></i></div></div>
                        @if ($canAward)
                            @if ($r['complete'] && ! $q->isExpired())<button type="button" class="btn btn-sm btn-p" style="margin-top:10px" wire:click="choose({{ $q->id }})"><x-icon name="award" :size="14" />Award</button>
                            @else<div class="muted sm" style="margin-top:10px">{{ $q->isExpired() ? 'Expired, cannot be awarded' : 'Incomplete, cannot be awarded alone' }}</div>@endif
                        @endif
                    </th>
                @endforeach
            </tr></thead><tbody>
                <tr class="sec"><td class="rh">Line items</td>@foreach ($rows as $r)<td></td>@endforeach</tr>
                @foreach ($rfq->items as $item)
                    <tr><th scope="row" class="rh">{{ $item->name }}<div class="t2" style="font-weight:400">{{ $item->qty() }} {{ $item->unit }}</div></th>
                        @foreach ($rows as $r)
                            @php $qi = $prices[$r['quote']->id][$item->id] ?? null; $lo = $low[$item->id] ?? null; $has = $qi && $qi->unit_price_cents !== null; $isLow = $has && $qi->unit_price_cents === $lo; @endphp
                            <td class="{{ $isLow ? 'best' : '' }}">@if ($has)<span class="price">{{ Money::format($qi->unit_price_cents, $r['quote']->currency) }}</span>@if ($isLow)<span class="lowtag">Lowest</span>@elseif ($lo)<span class="delta">+{{ number_format(intdiv(($qi->unit_price_cents - $lo) * 1000, $lo) / 10, 1) }}%</span>@endif<div class="tot">{{ Money::format($qi->total_cents, $r['quote']->currency) }}</div>@else<span class="nq">Not quoted</span>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="sec"><td class="rh">Totals</td>@foreach ($rows as $r)<td></td>@endforeach</tr>
                <tr><th scope="row" class="rh">Total excl. tax</th>@foreach ($rows as $r)<td class="{{ $r['rank'] === 1 ? 'best' : '' }}"><span class="big">{{ $cur($r) }}</span>@unless ($r['complete'])<div class="tot">{{ $r['coverage'] }} of {{ $n }} items</div>@endunless</td>@endforeach</tr>
                <tr><th scope="row" class="rh">Against budget</th>@foreach ($rows as $r)<td class="mono">@if ($rfq->budget_cents && $r['complete'] && $r['quote']->currency === $rfq->currency){{ $r['quote']->total_cents <= $rfq->budget_cents ? Money::format($rfq->budget_cents - $r['quote']->total_cents, $rfq->currency, false) . ' under' : Money::format($r['quote']->total_cents - $rfq->budget_cents, $rfq->currency, false) . ' over' }}@else<span class="faint">-</span>@endif</td>@endforeach</tr>
                <tr class="sec"><td class="rh">Terms</td>@foreach ($rows as $r)<td></td>@endforeach</tr>
                <tr><th scope="row" class="rh">Delivery</th>@foreach ($rows as $r)<td class="mono">{{ $r['quote']->delivery_days }} days</td>@endforeach</tr>
                <tr><th scope="row" class="rh">Payment terms</th>@foreach ($rows as $r)<td>{{ $r['quote']->payment_terms ?: '-' }}</td>@endforeach</tr>
                <tr><th scope="row" class="rh">Currency</th>@foreach ($rows as $r)<td>{{ $r['quote']->currency }}</td>@endforeach</tr>
                <tr><th scope="row" class="rh">Valid until</th>@foreach ($rows as $r)<td>{{ $r['quote']->valid_until?->format('j M Y') }}@if ($r['quote']->isExpired()) <x-badge status="bad" label="Expired" />@endif</td>@endforeach</tr>
                <tr><th scope="row" class="rh">Supplier rating</th>@foreach ($rows as $r)<td><x-stars :tenths="$r['quote']->supplier->rating_tenths" /></td>@endforeach</tr>
                <tr class="sec"><td class="rh">Score</td>@foreach ($rows as $r)<td></td>@endforeach</tr>
                <tr><th scope="row" class="rh">Weighted score<div class="t2" style="font-weight:400">price · quality · delivery · terms</div></th>@foreach ($rows as $r)<td class="{{ $r['rank'] === 1 ? 'best' : '' }}"><span class="big">{{ number_format($r['score'], 1) }}</span><div class="tot mono">{{ round($r['price']) }} · {{ round($r['quality']) }} · {{ round($r['delivery']) }} · {{ round($r['terms']) }}</div></td>@endforeach</tr>
            </tbody></table></div>
        </section>

        @if ($plan)
            <div style="margin-top:16px"><x-card title="Or award item by item" :sub="'Each item to its cheapest supplier · ' . $plan->count() . ' purchase order(s)'" :flush="true">
                <x-slot:act>@if ($canAward)<button type="button" class="btn btn-sm" wire:click="open('split')"><x-icon name="cols" :size="14" />Award item by item</button>@endif</x-slot:act>
                <ul class="list">@foreach ($plan as $row)<li><div class="grow"><div class="t1">{{ $row['quote']->supplier->name }}</div><div class="muted sm">{{ $row['lines']->count() }} of {{ $n }} item(s)</div></div><b class="mono">{{ Money::format($row['total'], $row['quote']->currency) }}</b></li>@endforeach
                    <li><div class="grow b">Total when split</div><b class="mono">{{ Money::format($plan->sum('total'), $rfq->currency) }}</b>@if ($lowest && $lowest->total_cents > $plan->sum('total'))<x-badge status="ok" :label="Money::format($lowest->total_cents - $plan->sum('total'), $rfq->currency, false) . ' less than one supplier'" />@endif</li></ul>
            </x-card></div>
        @elseif ($planError)
            <div style="margin-top:16px"><x-note kind="info">{{ $planError }}</x-note></div>
        @endif
    @endif

    @if ($modal === 'award' && $picked)
        <x-modal title="Award this request?">
            <p>Award <b class="mono">{{ $rfq->number }}</b> to <b>{{ $picked['quote']->supplier->name }}</b> for <b class="mono">{{ Money::format($picked['quote']->total_cents, $picked['quote']->currency) }}</b>. A draft purchase order is created and every supplier is told the outcome.</p>
            @if ($needsWhy)
                <div style="margin:12px 0"><x-note kind="warn">This is not the lowest complete quotation ({{ $lowest->supplier->name }}: {{ Money::format($lowest->total_cents, $lowest->currency) }}). Explain the choice.</x-note></div>
                <x-field label="Why this supplier" for="why" help="At least {{ \App\Services\AwardService::MIN_JUSTIFICATION }} characters. Recorded on the purchase order."><textarea id="why" class="textarea" rows="3" wire:model="justification" autofocus></textarea></x-field>
            @else
                <div style="margin-top:12px"><x-field label="Note" for="why" :opt="true"><textarea id="why" class="textarea" rows="2" wire:model="justification"></textarea></x-field></div>
            @endif
            <x-slot:foot><button type="button" class="btn" wire:click="close">Not yet</button><button type="button" class="btn btn-p" wire:click="award" wire:loading.attr="disabled"><x-icon name="award" />Award and create order</button></x-slot:foot>
        </x-modal>
    @elseif ($modal === 'split' && $plan)
        <x-modal title="Award item by item?">
            <p><b class="mono">{{ $rfq->number }}</b> will be split into {{ $plan->count() }} draft purchase order(s), each item going to the supplier with the lowest price for it:</p>
            <ul style="margin:10px 0 0 18px">@foreach ($plan as $row)<li><b>{{ $row['quote']->supplier->name }}</b> - {{ $row['lines']->count() }} item(s), {{ Money::format($row['total'], $row['quote']->currency) }}</li>@endforeach</ul>
            <x-slot:foot><button type="button" class="btn" wire:click="close">Not yet</button><button type="button" class="btn btn-p" wire:click="splitAward" wire:loading.attr="disabled">Award and create orders</button></x-slot:foot>
        </x-modal>
    @endif
    @if ($modal === 'bafo')
        <x-modal title="Ask for best and final offers">
            <p class="muted" style="margin-bottom:12px">Every supplier with a quotation may revise it once more until the deadline. They do not see each other's prices.</p>
            <div class="stack" style="gap:14px"><x-field label="Deadline" for="bf-d"><input id="bf-d" type="datetime-local" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="bafo.deadline"></x-field>
                <x-field label="Message to suppliers" for="bf-m" :opt="true"><textarea id="bf-m" class="textarea" rows="2" wire:model="bafo.message" placeholder="e.g. We are close to a decision. Please send your best price."></textarea></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="openBafo">Open the round</button></x-slot:foot>
        </x-modal>
    @elseif ($modal === 'auction')
        <x-modal title="Start a reverse auction">
            <p class="muted" style="margin-bottom:12px">Suppliers with a complete quotation lower their totals against each other. Each sees its own rank, never the others' prices. A bid in the last 2 minutes extends the auction by 2 minutes.</p>
            <div class="fgrid"><x-field label="Length in minutes" for="au-m" help="5 to 240"><input id="au-m" class="input mono" inputmode="numeric" wire:model="auc.minutes"></x-field><x-field label="Smallest step, %" for="au-s" help="How much lower each new bid must be"><input id="au-s" class="input mono" inputmode="decimal" wire:model="auc.step"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="startAuction" wire:loading.attr="disabled">Start now</button></x-slot:foot>
        </x-modal>
    @endif
</div>
