@props(['rows','role'=>'buyer'])
<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ $role === 'seller' ? __('My markets') : __('Markets') }}</h1>
            <p class="ph-d">{{ __('Browse market locations and their current product listings.') }}</p></div>
    </header>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search"><x-source.icon name="search" :size="16" />
                    <span class="sr">{{ __('Search markets') }}</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name or location') }}"></label>
                <div class="dt-tools"><span class="sm muted">{{ $rows->total() }} {{ __('markets') }}</span>
                    <label for="market-pagesize" class="sr">{{ __('Rows per page') }}</label>
                    <select id="market-pagesize" class="select" wire:model.live="quantity">
                        <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                    </select>
                </div>
            </div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Market') }}</th><th>{{ __('Location') }}</th><th>{{ __('Seller') }}</th><th class="num">{{ __('Products') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                @forelse($rows as $market)
                    <tr wire:key="market-{{ $role }}-{{ $market->id }}">
                        <td class="t1">{{ $market->name }}</td><td class="sm">{{ $market->location ?: '—' }}</td>
                        <td class="sm">{{ $market->seller?->company_name ?: $market->seller?->name ?: '—' }}</td>
                        <td class="num mono">{{ $market->products_count ?? 0 }}</td>
                        <td><a class="btn btn-sm" href="{{ route($role === 'seller' ? 'seller.markets.show' : ($role === 'supplier' ? 'supplier.markets.show' : ($role === 'admin' ? 'markets.show' : 'buyer.markets.show')), $market) }}">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty"><h3>{{ __('No markets found') }}</h3></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="dt-foot">{{ $rows->links() }}</div>
        </div>
    </section>
</div>
