@php use App\Support\Money; use App\Models\MarketOrder; $bv = ['placed' => 'info', 'confirmed' => 'warn', 'ready' => 'warn', 'delivered' => 'ok', 'cancelled' => 'bad']; $at = array_search($o->status, MarketOrder::FLOW, true); @endphp
<div>
    <x-ph :title="$selling ? ($o->supplier?->name ?? $o->buyer->name) : $o->store->name" :crumbs="[[$selling ? 'Sales' : 'Marketplace orders', route('market.orders.index')], [$o->number]]" :recent="$o->number" :recent-sub="$o->store->name">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $o->number }}</span><x-badge :status="$bv[$o->status]" :label="$o->statusLabel()" /><span class="tag">Marketplace</span></div></x-slot:pre>
        <a class="btn" href="{{ route('market.orders.pdf', $o) }}" target="_blank" rel="noopener"><x-icon name="download" />PDF</a>
        @if ($canCancel)<button type="button" class="btn btn-d" wire:click="open('cancel')">Cancel order</button>@endif
        @if ($next)<button type="button" class="btn btn-p" wire:click="advance" wire:loading.attr="disabled"><x-icon name="check" />{{ $next['label'] }}</button>@endif
    </x-ph>
    <x-facts :items="[['Order total', Money::format($o->total_cents, $o->currency), '', true], ['Store', $o->store->name, $o->store->market->name], ['Buyer', $o->supplier?->name ?? $o->buyer->name], ['Placed', $o->created_at->format('j M Y, H:i')], ['Deliver to', $o->deliver_to ?? '-']]" />
    @if ($o->status === 'cancelled')<div style="margin-bottom:16px"><x-note kind="bad"><b>Cancelled</b> on {{ $o->cancelled_at->format('j M Y') }}. {{ $o->cancel_reason }}</x-note></div>
    @else
        <x-life label="Order progress" :steps="['Placed', 'Confirmed', 'Picked and ready', 'Delivered']" :at="$at" :final="$o->status === 'delivered'">
            <x-slot:next>
                @switch($o->status)
                    @case('placed') {{ $selling ? 'Next step: confirm the order so the goods can be picked.' : 'Waiting for the store to confirm.' }} @break
                    @case('confirmed') {{ $selling ? 'Next step: pick the goods and mark the order ready.' : 'The store is picking your goods.' }} @break
                    @case('ready') {{ $selling ? 'Next step: deliver and mark it delivered.' : 'Picked and on its way to you.' }} @break
                    @case('delivered') Delivered {{ $o->delivered_at->format('j M Y, H:i') }}. @break
                @endswitch
            </x-slot:next>
            @if ($o->rfq && ! $selling)<a class="btn btn-sm" href="{{ auth()->user()->role === 'supplier' ? route('quotes.form', $o->rfq) : route('rfqs.show', $o->rfq) }}" wire:navigate>Open {{ $o->rfq->number }}</a>@endif
        </x-life>
    @endif
    <div class="grid g-2-1">
        <x-card title="Items" :sub="$o->items->count() . ' line(s)'" :flush="true"><div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Product</th><th class="num">Quantity</th><th class="num">Unit price</th><th class="num">Total</th></tr></thead><tbody>
            @foreach ($o->items as $l)<tr><td class="tmain"><div class="t1">{{ $l->name }}</div><div class="t2 mono">{{ $l->sku }}</div></td><td class="num mono">{{ Money::qty($l->quantity_milli) }} <span class="faint">{{ $l->unit }}</span></td><td class="num mono">{{ Money::format($l->unit_price_cents, $o->currency) }}</td><td class="num mono b">{{ Money::format($l->total_cents, $o->currency) }}</td></tr>@endforeach
            <tr><td class="b" colspan="3">Order total</td><td class="num mono b" style="font-size:15px">{{ Money::format($o->total_cents, $o->currency) }}</td></tr>
        </tbody></table></div>@if ($o->note)<div class="sm muted" style="padding:10px 16px;border-top:1px solid var(--line)">Note from the buyer: {{ $o->note }}</div>@endif</x-card>
        <div class="stack">
            <x-card title="Store tasks" :flush="true">@if ($o->tasks->isEmpty())<div class="card-b faint">Tasks start when the store confirms the order.</div>@else<ul class="list">@foreach ($o->tasks as $t)<li><div class="grow"><div class="t1">{{ $t->type === 'pick' ? 'Pick the goods' : 'Deliver to the buyer' }}</div><div class="muted sm">{{ $t->status === 'done' ? 'Done by ' . ($t->doer?->name ?? 'the store') . ' · ' . $t->done_at->format('j M, H:i') : ($t->status === 'cancelled' ? 'Cancelled' : 'Open') }}</div></div><x-badge :status="$t->status === 'done' ? 'ok' : ($t->status === 'cancelled' ? 'bad' : 'warn')" :label="ucfirst($t->status)" /></li>@endforeach</ul>@endif</x-card>
            <x-card title="History"><x-history :logs="$logs" /></x-card>
        </div>
    </div>
    @if ($modal === 'cancel')
        <x-modal title="Cancel this order?"><p style="margin-bottom:12px"><b class="mono">{{ $o->number }}</b> is cancelled and its stock goes back to the store.</p><x-field label="Reason" for="mo-r"><textarea id="mo-r" class="textarea" rows="2" wire:model="reason" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Keep the order</button><button type="button" class="btn btn-d solid" wire:click="cancel">Cancel order</button></x-slot:foot></x-modal>
    @endif
</div>
