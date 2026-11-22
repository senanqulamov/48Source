@props(['order', 'role' => 'admin'])
@php
    $back = $role === 'seller' ? 'seller.orders.index' : ($role === 'supplier' ? 'supplier.orders.index' : 'orders.index');
    $status = strtolower($order->status);
@endphp
<div class="stack">
    <header class="ph">
        <div><div class="row sm muted"><a class="link" href="{{ route($back) }}">{{ __('Orders') }}</a><span>/</span><span class="mono">{{ $order->order_number }}</span></div>
            <h1 class="ph-t">{{ __('Order') }} {{ $order->order_number }}</h1>
            <p class="ph-d">{{ __('Placed') }} {{ $order->created_at?->format('d M Y, H:i') ?? '—' }}</p></div>
        <div class="ph-a"><span class="badge {{ $status === 'completed' ? 'b-ok' : ($status === 'cancelled' || $status === 'rejected' ? 'b-bad' : 'b-info') }}">{{ ucfirst($order->status) }}</span>
            @if($role === 'supplier' && $order->status === 'pending')
                <a class="btn" href="{{ route('supplier.orders.edit', $order) }}">{{ __('Edit order') }}</a>
                <button class="btn btn-d" type="button" wire:click="confirmDelete">{{ __('Delete') }}</button>
            @endif
        </div>
    </header>
    <div class="facts">
        <div class="fact"><span>{{ __('Order total') }}</span><b class="mono">{{ number_format((float)$order->total,2) }}</b></div>
        <div class="fact"><span>{{ __('Items') }}</span><b class="mono">{{ $order->items->count() }}</b></div>
        <div class="fact"><span>{{ __('Status') }}</span><b>{{ ucfirst($order->status) }}</b></div>
        <div class="fact"><span>{{ __('Buyer') }}</span><b>{{ $order->user?->name ?? '—' }}</b></div>
    </div>
    <div class="grid g-2-1">
        <section class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Ordered items') }}</h2></div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Market') }}</th><th class="num">{{ __('Quantity') }}</th><th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Subtotal') }}</th></tr></thead>
                <tbody>
                @forelse($order->items as $line)
                    <tr><td class="t1">{{ $line->product?->name ?? __('Unknown product') }}</td>
                        <td class="sm">{{ $line->market?->name ?? '—' }}</td>
                        <td class="num mono">{{ $line->quantity }}</td>
                        <td class="num mono">{{ number_format((float)$line->unit_price,2) }}</td>
                        <td class="num mono">{{ number_format((float)$line->subtotal,2) }}</td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty">{{ __('No items recorded.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
        <aside class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Order details') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Buyer') }}</dt><dd>{{ $order->user?->name ?? '—' }}</dd>
                    <dt>{{ __('Notes') }}</dt><dd>{{ $order->notes ?: '—' }}</dd>
                    <dt>{{ __('Seller notes') }}</dt><dd>{{ $order->seller_notes ?: '—' }}</dd>
                    <dt>{{ __('Total') }}</dt><dd class="mono b">{{ number_format((float)$order->total,2) }}</dd>
                </dl></div>
            </section>
            @if($role === 'seller' && $order->isPending())
                <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Respond to order') }}</h2></div>
                    <div class="card-b stack">
                        <div class="field"><label for="seller-order-notes">{{ __('Seller notes / reason for rejection') }}</label>
                            <textarea id="seller-order-notes" class="textarea" rows="4" wire:model="sellerNotes"></textarea></div>
                        <div class="row wrap"><button type="button" class="btn btn-p" wire:click="confirmAccept">{{ __('Accept order') }}</button>
                            <button type="button" class="btn btn-d" wire:click="confirmReject">{{ __('Reject order') }}</button></div>
                        <p class="muted sm">{{ __('Rejecting an order restores the item stock through the existing workflow.') }}</p>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>