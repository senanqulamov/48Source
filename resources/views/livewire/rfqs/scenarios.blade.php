@php use App\Support\Money; @endphp
<div>
    <x-ph title="Award scenarios" :crumbs="[[auth()->user()->isAdmin() ? 'RFQs' : 'My RFQs', route('rfqs.index')], [$rfq->number, route('rfqs.show', $rfq)], ['Scenarios']]">
        <a class="btn btn-p" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="cols" />Open comparison</a>
    </x-ph>
    <p class="ph-d" style="margin:-10px 0 16px"><span class="mono">{{ $rfq->number }}</span> · {{ $rfq->title }}. {{ $scenarios->count() }} way{{ $scenarios->count() === 1 ? '' : 's' }} to award, side by side{{ $rfq->budget_cents ? ', against a budget of ' . Money::format($rfq->budget_cents, $rfq->currency, false) : '' }}.</p>
    @if ($scenarios->isEmpty())
        <x-card><x-empty icon="cols" title="Nothing to compare yet" text="Scenarios need at least one complete, valid quotation." /></x-card>
    @else
        <div class="verdicts" style="grid-template-columns:repeat({{ $scenarios->count() }},minmax(0,1fr))">
            @foreach ($scenarios as $s)
                <div class="verdict {{ $s['total'] === $min ? 'best' : '' }}"><h4>{{ $s['name'] }}</h4><b>{{ Money::format($s['total'], $cur) }}</b>
                    <div class="muted sm" style="margin-top:4px">{{ $s['who'] }} · {{ $s['orders'] }} order{{ $s['orders'] === 1 ? '' : 's' }} · {{ $s['days'] }} days</div>
                    <div class="sm" style="margin-top:6px">@if ($s['total'] === $min)<span class="badge b-ok plain">Lowest total</span>@else<span class="badge b-warn plain">+{{ Money::format($s['total'] - $min, $cur, false) }}</span>@endif @if ($rfq->budget_cents)<span class="badge {{ $s['total'] <= $rfq->budget_cents ? 'b-ok' : 'b-bad' }} plain">{{ $s['total'] <= $rfq->budget_cents ? 'Within budget' : 'Over budget' }}</span>@endif</div>
                    <p class="muted sm" style="margin-top:8px">{{ $s['note'] }}</p></div>
            @endforeach
        </div>
        @if ($plan)
            <x-card title="Cheapest supplier for each item" :sub="$plan->count() . ' purchase orders if awarded this way'" :flush="true">
                <div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Item</th><th class="num">Quantity</th><th>Cheapest supplier</th><th class="num">Unit price</th><th class="num">Line total</th></tr></thead><tbody>
                    @foreach ($plan as $row)@foreach ($row['lines'] as $l)<tr><td class="t1">{{ $l->rfqItem->name }}</td><td class="num mono">{{ $l->rfqItem->qty() }} <span class="faint">{{ $l->rfqItem->unit }}</span></td><td>{{ $row['quote']->supplier->name }}</td><td class="num mono">{{ Money::format($l->unit_price_cents, $row['quote']->currency) }}</td><td class="num mono b">{{ Money::format($l->total_cents, $row['quote']->currency) }}</td></tr>@endforeach @endforeach
                    <tr><td class="b" colspan="4">Total when split</td><td class="num mono b">{{ Money::format((int) $plan->sum('total'), $cur) }}</td></tr>
                    @if ($lowest)<tr><td colspan="4">Against the lowest single supplier ({{ $lowest->supplier->name }})</td><td class="num mono" style="color:var(--ok)">{{ $lowest->total_cents > $plan->sum('total') ? '-' . Money::format($lowest->total_cents - (int) $plan->sum('total'), $cur) : 'no saving' }}</td></tr>@endif
                </tbody></table></div>
            </x-card>
        @elseif ($planError)
            <x-note kind="info">Item-by-item award is not possible here: {{ $planError }}</x-note>
        @endif
        <p class="muted sm" style="margin-top:12px">Scenarios only show the options. Awarding happens on the comparison page.</p>
    @endif
</div>
