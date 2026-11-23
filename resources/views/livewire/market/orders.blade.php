@php
    use App\Models\MarketOrder; use App\Support\Money; $bv = ['placed' => 'info', 'confirmed' => 'warn', 'ready' => 'warn', 'delivered' => 'ok', 'cancelled' => 'bad'];
    $quick = ['' => ['All', $counts->sum()]]; foreach (MarketOrder::STATUSES as $k => $l) { if (($counts[$k] ?? 0) || $k === 'placed') { $quick[$k] = [$l, $counts[$k] ?? 0]; } }
@endphp
<div>
    <x-ph :title="$selling ? 'Sales' : 'Marketplace orders'" :desc="$selling ? 'Orders buyers placed with your store. Confirm them, then pick and deliver.' : 'What you bought in the marketplace, one order per store.'" :crumbs="[[$selling ? 'Sales' : 'Marketplace orders']]">
        @unless ($selling)@if (auth()->user()->canShop())<a class="btn btn-p" href="{{ route('market.shop') }}" wire:navigate><x-icon name="store" />Open marketplace</a>@endif @endunless
    </x-ph>
    <x-dt :lw="$this" :rows="$rows" id="mo" search="Search order, store or product" label="Search marketplace orders" noun="orders" :all="$all" :quick="$quick" :export="true">
        <x-slot:empty><x-empty icon="store" :title="$selling ? 'No sales yet' : 'No marketplace orders yet'" :text="$selling ? 'Orders appear here as soon as a buyer places one.' : 'Put products in the basket and place your first order.'" /></x-slot:empty>
        <x-slot:head><x-th col="number">Order</x-th><th>{{ $selling ? 'Buyer' : 'Store' }}</th><th class="hide-md">For request</th><th class="num hide-sm">Lines</th><x-th col="total_cents" :num="true">Amount</x-th><x-th col="created_at" :sort="$sort ?: 'created_at'" cls="hide-sm">Placed</x-th><x-th col="status">Status</x-th></x-slot:head>
        @foreach ($rows as $o)
            <tr wire:key="mo-{{ $o->id }}" class="click" data-href="{{ route('market.orders.show', $o) }}" tabindex="0"><td class="mono b" style="white-space:nowrap">{{ $o->number }}</td>
                <td class="tmain"><div class="t1">{{ $selling ? ($o->supplier?->name ?? $o->buyer->name) : $o->store->name }}</div><div class="t2">{{ $selling ? $o->buyer->name : $o->store->market->name }}</div></td>
                <td class="hide-md mono">{{ $o->rfq?->number ?? '-' }}</td><td class="num mono hide-sm">{{ $o->items_count }}</td><td class="num mono b">{{ Money::format($o->total_cents, $o->currency) }}</td>
                <td class="hide-sm" style="white-space:nowrap">{{ $o->created_at->format('j M Y') }}</td><td><x-badge :status="$bv[$o->status]" :label="$o->statusLabel()" /></td></tr>
        @endforeach
    </x-dt>
</div>
