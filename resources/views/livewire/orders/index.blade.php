<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('Marketplace orders') }}</h1>
            <p class="ph-d">{{ __('Review purchases, fulfillment statuses and seller activity.') }}</p></div>
        <div class="ph-a"><livewire:orders.create @created="$refresh"/></div>
    </header>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search">
                    <x-source.icon name="search" :size="16"/>
                    <span class="sr">{{ __('Search orders') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Order reference, buyer or market') }}"/>
                </label>
                <div class="dt-tools">
                    <label for="admin-order-rows" class="sr">{{ __('Rows') }}</label>
                    <select id="admin-order-rows" class="select" wire:model.live="quantity">
                        <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                    </select>
                </div>
            </div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr>
                    <th>{{ __('Reference') }}</th><th>{{ __('Buyer') }}</th><th>{{ __('Markets') }}</th>
                    <th class="num">{{ __('Amount') }}</th><th>{{ __('Status') }}</th>
                    <th>{{ __('Created') }}</th><th>{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @forelse($this->rows as $order)
                    <tr wire:key="admin-order-row-{{ $order->id }}">
                        <td><a href="{{ route('orders.show', $order) }}" class="link t1 mono">{{ $order->order_number }}</a></td>
                        <td class="sm">{{ $order->user?->name ?? '—' }}</td>
                        <td class="sm">{{ $order->items->pluck('market.name')->filter()->unique()->implode(', ') ?: '—' }}</td>
                        <td class="num mono">{{ number_format((float)$order->total, 2) }}</td>
                        <td><span class="badge {{ $order->status === 'completed' ? 'b-ok' : ($order->status === 'cancelled' ? 'b-bad' : 'b-info') }}">{{ ucfirst($order->status) }}</span></td>
                        <td class="mono sm">{{ $order->created_at?->format('d M Y') ?? '—' }}</td>
                        <td><div class="row wrap">
                            <button type="button" class="btn btn-sm" wire:click="$dispatch('view::order', { order: '{{ $order->id }}' })">{{ __('Quick view') }}</button>
                            @can('edit_orders')
                                <button type="button" class="btn btn-sm" wire:click="$dispatch('load::order', { order: '{{ $order->id }}' })">{{ __('Edit') }}</button>
                            @endcan
                            @can('delete_orders')
                                <livewire:orders.delete :order="$order" :key="'admin-order-delete-'.$order->id" @deleted="$refresh"/>
                            @endcan
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty">{{ __('No orders found.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="dt-foot">{{ $this->rows->links() }}</div>
        </div>
    </section>
    <livewire:orders.update @updated="$refresh"/>
    <livewire:orders.view-order />
</div>