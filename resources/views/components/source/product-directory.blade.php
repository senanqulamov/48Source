@props(['rows','role' => 'buyer','marketOptions' => null,'categoryOptions' => null])
<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ $role === 'seller' ? __('My product catalog') : __('Products') }}</h1>
            <p class="ph-d">{{ $role === 'seller' ? __('Manage the products offered through your markets.') : __('Browse the live product catalog by market and category.') }}</p></div>
        <div class="ph-a">
            @if(in_array($role,['seller','admin'],true))
                <button type="button" class="btn btn-p" wire:click="$dispatch('products::create::open')">+ {{ __('Add product') }}</button>
            @endif
        </div>
    </header>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search"><x-source.icon name="search" :size="16" />
                    <span class="sr">{{ __('Search products') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name, SKU or category') }}"></label>
                <div class="dt-tools">
                    @if($marketOptions !== null)
                        <label for="product-market" class="sr">{{ __('Market') }}</label>
                        <select id="product-market" class="select" wire:model.live="marketFilter">
                            <option value="">{{ __('All markets') }}</option>
                            @foreach($marketOptions as $market)
                                <option value="{{ $market->id }}">{{ $market->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if($categoryOptions !== null)
                        <label for="product-category" class="sr">{{ __('Category') }}</label>
                        <select id="product-category" class="select" wire:model.live="categoryFilter">
                            <option value="">{{ __('All categories') }}</option>
                            @foreach($categoryOptions as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <label for="product-pagesize" class="sr">{{ __('Rows per page') }}</label>
                    <select id="product-pagesize" class="select" wire:model.live="quantity">
                        <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                    </select>
                </div>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr>
                        <th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Category') }}</th>
                        <th>{{ __('Market') }}</th><th class="num">{{ __('Price') }}</th>
                        <th class="num">{{ __('Stock') }}</th><th>{{ __('Action') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($rows as $product)
                        <tr wire:key="catalog-{{ $role }}-{{ $product->id }}">
                            <td><span class="t1">{{ $product->name }}</span></td>
                            <td class="mono sm">{{ $product->sku ?: '—' }}</td>
                            <td class="sm">{{ $product->category?->name ?? '—' }}</td>
                            <td class="sm">{{ $product->market?->name ?? '—' }}</td>
                            <td class="num mono">{{ number_format((float) $product->price, 2) }}</td>
                            <td class="num"><span class="badge {{ $product->stock > 10 ? 'b-ok' : ($product->stock > 0 ? 'b-warn' : 'b-bad') }}">{{ $product->stock }}</span></td>
                            <td><a class="btn btn-sm" href="{{ route($role === 'seller' ? 'seller.products.show' : ($role === 'supplier' ? 'supplier.products.show' : ($role === 'admin' ? 'products.show' : 'buyer.products.show')), $product) }}">{{ __('View') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><h3>{{ __('No products found') }}</h3><p>{{ __('Adjust your filters or search term.') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dt-foot">{{ $rows->links() }}</div>
        </div>
    </section>
</div>
