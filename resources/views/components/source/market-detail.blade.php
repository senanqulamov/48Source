@props(['market', 'role' => 'admin'])
@php
    $back = $role === 'seller' ? 'seller.markets.index' : ($role === 'supplier' ? 'supplier.markets.index' : 'markets.index');
@endphp
<div class="stack">
    <header class="ph">
        <div><div class="row sm muted"><a class="link" href="{{ route($back) }}">{{ __('Markets') }}</a><span>/</span><span class="mono">#{{ $market->id }}</span></div>
            <h1 class="ph-t">{{ $market->name }}</h1><p class="ph-d">{{ $market->location ?: __('No location specified') }}</p></div>
        <div class="ph-a"><span class="badge b-info">{{ __('Marketplace') }}</span></div>
    </header>
    <div class="facts">
        <div class="fact"><span>{{ __('Products listed') }}</span><b class="mono">{{ $market->products()->count() }}</b></div>
        <div class="fact"><span>{{ __('Location') }}</span><b>{{ $market->location ?: '—' }}</b></div>
        <div class="fact"><span>{{ __('Owner') }}</span><b>{{ $market->seller?->company_name ?: $market->seller?->name ?: '—' }}</b></div>
        <div class="fact"><span>{{ __('Orders') }}</span><b class="mono">{{ $market->orderItems()->distinct()->count('order_id') }}</b></div>
        <div class="fact"><span>{{ __('Revenue') }}</span><b class="mono">{{ number_format((float) $market->orderItems()->sum('subtotal'), 2) }}</b></div>
    </div>
    <section class="card">
        <div class="card-h"><h2 class="card-t">{{ __('Products in this market') }}</h2><span class="card-s">{{ __('Current inventory') }}</span></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th class="num">{{ __('Price') }}</th><th class="num">{{ __('Stock') }}</th></tr></thead>
            <tbody>
            @forelse($market->products()->orderBy('name')->limit(50)->get() as $product)
                <tr><td class="t1">{{ $product->name }}</td><td class="mono sm">{{ $product->sku ?: '—' }}</td>
                    <td class="num mono">{{ number_format((float)$product->price,2) }}</td><td class="num mono">{{ $product->stock }}</td></tr>
            @empty
                <tr><td colspan="4"><div class="empty">{{ __('This market currently has no listed products.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if($market->products()->count() > 50)<div class="card-b muted sm">{{ __('Showing the first 50 products.') }}</div>@endif
    </section>
</div>