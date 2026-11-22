@props(['product', 'role' => 'admin'])
@php
    $back = $role === 'seller' ? 'seller.products.index' : ($role === 'supplier' ? 'supplier.products.index' : 'products.index');
    $attributes = $product->attributes;
@endphp
<div class="stack">
    <header class="ph">
        <div>
            <div class="row sm muted"><a class="link" href="{{ route($back) }}">{{ __('Products') }}</a><span>/</span><span class="mono">#{{ $product->id }}</span></div>
            <h1 class="ph-t">{{ $product->name }}</h1>
            <p class="ph-d">{{ $product->sku ?: __('No SKU') }} · {{ $product->category?->name ?? __('Uncategorized') }}</p>
        </div>
        <div class="ph-a"><span class="badge {{ $product->stock > 0 ? 'b-ok' : 'b-bad' }}">{{ $product->stock > 0 ? __('In stock') : __('Out of stock') }}</span></div>
    </header>
    <div class="facts">
        <div class="fact"><span>{{ __('Unit price') }}</span><b class="mono">{{ number_format((float) $product->price,2) }}</b></div>
        <div class="fact"><span>{{ __('Stock') }}</span><b class="mono">{{ number_format($product->stock) }}</b></div>
        <div class="fact"><span>{{ __('SKU') }}</span><b class="mono">{{ $product->sku ?: '—' }}</b></div>
        <div class="fact"><span>{{ __('Category') }}</span><b>{{ $product->category?->name ?? '—' }}</b></div>
    </div>
    <div class="grid g-2-1">
        <section class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Product specifications') }}</h2></div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Attribute') }}</th><th>{{ __('Value') }}</th></tr></thead>
                <tbody>
                @forelse($attributes as $attribute)
                    <tr><td class="t1">{{ $attribute->name }}</td><td>{{ $attribute->value }}</td></tr>
                @empty
                    <tr><td colspan="2"><div class="empty">{{ __('No additional specifications recorded.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
        <aside class="stack">
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Market & supplier') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Market') }}</dt><dd>{{ $product->market?->name ?? '—' }}</dd>
                    <dt>{{ __('Market location') }}</dt><dd>{{ $product->market?->location ?? '—' }}</dd>
                    <dt>{{ __('Supplier') }}</dt><dd>{{ $product->supplier?->company_name ?: $product->supplier?->name ?: '—' }}</dd>
                </dl></div>
            </section>
            <div class="note">{{ __('Availability and price are sourced from the live product record.') }}</div>
        </aside>
    </div>
</div>