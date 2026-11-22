@props(['rows','seller'=>false])
<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ $seller ? __('Seller orders') : __('My orders') }}</h1>
            <p class="ph-d">{{ __('Review order details, fulfillment progress and activity.') }}</p>
        </div>
        <div class="ph-a">
            @if(!$seller && \Illuminate\Support\Facades\Route::has('supplier.orders.create'))
                <a class="btn btn-p" href="{{ route('supplier.orders.create') }}">+ {{ __('Create order') }}</a>
            @endif
        </div>
    </header>
    <div class="note">{{ __('These are existing marketplace orders. The new procurement purchase-order lifecycle is being implemented separately.') }}</div>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search">
                    <x-source.icon name="search" :size="16" />
                    <span class="sr">{{ __('Search orders') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search order number or status') }}" />
                </label>
                <div class="dt-tools"><span class="sm muted">{{ $rows->total() }} {{ __('orders') }}</span></div>
            </div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr>
                    <th>{{ __('Order number') }}</th>
                    <th>{{ $seller ? __('Market') : __('Buyer') }}</th>
                    <th class="num">{{ __('Total') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th>{{ __('Action') }}</th>
                </tr></thead>
                <tbody>
                @forelse($rows as $order)
                    <tr wire:key="order-{{ $order->id }}">
                        <td><span class="t1 mono">{{ $order->order_number }}</span><div class="t2">#{{ $order->id }}</div></td>
                        <td>
                            @if($seller)
                                {{ $order->items->pluck('market.name')->filter()->unique()->implode(', ') ?: '—' }}
                            @else
                                {{ $order->user?->name ?? '—' }}
                            @endif
                        </td>
                        <td class="num mono">{{ number_format((float) $order->total,2) }}</td>
                        <td>
                            @php($orderStatus = strtolower($order->status))
                            <span class="badge {{ $orderStatus === 'completed' ? 'b-ok' : ($orderStatus === 'cancelled' ? 'b-bad' : ($orderStatus === 'processing' ? 'b-info' : '')) }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>
                        <td class="mono sm">{{ $order->created_at?->format('d M Y') ?? '—' }}</td>
                        <td><a class="btn btn-sm" href="{{ route($seller ? 'seller.orders.show' : 'supplier.orders.show',$order) }}">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><h3>{{ __('No orders found') }}</h3><p>{{ __('Orders will appear here once created.') }}</p></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="dt-foot">{{ $rows->links() }}</div>
        </div>
    </section>
</div>
