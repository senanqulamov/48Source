<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Procurement purchase orders') }}</h1>
        <p class="ph-d">{{ __('Award-based purchase orders are separate from marketplace sales orders.') }}</p></div></header>
    <section class="card"><div class="dt">
        <div class="dt-top"><label class="dt-search"><x-source.icon name="search" :size="16"/><span class="sr">{{ __('Search PO') }}</span>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('PO number') }}"></label></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('PO') }}</th><th>{{ __('RFQ') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th><th>{{ __('Created') }}</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr wire:key="procurement-po-{{ $order->id }}">
                    <td><a class="link t1 mono" href="{{ route('procurement.orders.show', $order) }}">PO-{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</a></td>
                    <td class="mono">RFQ #{{ $order->request_id }}</td>
                    <td class="sm">{{ $order->supplier?->company_name ?: $order->supplier?->name ?: '—' }}</td>
                    <td><span class="badge {{ $order->status === 'invoiced' ? 'b-ok' : 'b-info' }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></td>
                    <td class="num mono">{{ $order->currency }} {{ number_format((float)$order->total_amount, 2) }}</td>
                    <td class="sm mono">{{ $order->created_at?->format('d M Y') }}</td>
                </tr>
            @empty<tr><td colspan="6"><div class="empty">{{ __('No procurement purchase orders yet.') }}</div></td></tr>@endforelse
            </tbody>
        </table></div><div class="dt-foot">{{ $orders->links() }}</div>
    </div></section>
</div>