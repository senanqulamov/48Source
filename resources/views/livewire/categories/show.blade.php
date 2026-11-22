<div class="stack">
    <header class="ph">
        <div><div class="row sm muted"><a class="link" href="{{ route('categories.index') }}">{{ __('Categories') }}</a><span>/</span><span class="mono">#{{ $category->id }}</span></div>
            <h1 class="ph-t">{{ $category->name }}</h1><p class="ph-d">{{ __('Category products and suppliers') }}</p></div>
        <div class="ph-a"><button type="button" class="btn" wire:click="$dispatch('load::category', { category: '{{ $category->id }}' })">{{ __('Edit category') }}</button></div>
    </header>
    <div class="facts"><div class="fact"><span>{{ __('Products') }}</span><b class="mono">{{ $category->products()->count() }}</b></div></div>
    <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Products in category') }}</h2></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Market') }}</th><th class="num">{{ __('Price') }}</th><th class="num">{{ __('Stock') }}</th></tr></thead>
            <tbody>
            @forelse($this->products as $product)
                <tr wire:key="category-product-{{ $product->id }}">
                    <td><a class="t1 link" href="{{ route('products.show', $product) }}">{{ $product->name }}</a></td>
                    <td class="mono sm">{{ $product->sku ?: '—' }}</td>
                    <td class="sm">{{ $product->market?->name ?? '—' }}</td>
                    <td class="num mono">{{ number_format((float)$product->price,2) }}</td>
                    <td class="num mono">{{ $product->stock }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty">{{ __('No products in this category.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="dt-foot">{{ $this->products->links() }}</div>
    </section>
    <livewire:categories.update @updated="$refresh" />
</div>