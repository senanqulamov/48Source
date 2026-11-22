<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('Seller workspace') }}</h1><p class="ph-d">{{ __('Monitor your markets, listings and customer orders.') }}</p></div>
        <div class="ph-a"><a href="{{ route('seller.products.index') }}" class="btn btn-p">{{ __('Manage products') }}</a></div>
    </header>
    <div class="strip">
        <a class="strip-i" href="{{ route('seller.markets.index') }}"><span class="strip-l">{{ __('Active markets') }}</span><span class="strip-v">{{ $activeMarkets }}</span><span class="strip-s">{{ __('Markets with your products') }}</span></a>
        <a class="strip-i" href="{{ route('seller.products.index') }}"><span class="strip-l">{{ __('Products listed') }}</span><span class="strip-v">{{ $productsListed }}</span><span class="strip-s">{{ __('Across your catalog') }}</span></a>
        <a class="strip-i" href="{{ route('seller.orders.index') }}"><span class="strip-l">{{ __('Pending orders') }}</span><span class="strip-v">{{ $pendingOrders }}</span><span class="strip-s">{{ __('Waiting for action') }}</span></a>
        <div class="strip-i"><span class="strip-l">{{ __('Completed sales') }}</span><span class="strip-v">{{ $totalSales }}</span><span class="strip-s">{{ __('Orders marked completed') }}</span></div>
    </div>
    <div class="grid g-2-1">
        <section class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Sales overview') }}</h2></div>
            <div class="card-b stack">
                <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Revenue from completed orders') }}</span><b class="mono">{{ number_format($totalRevenue, 2) }}</b></div>
                <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Commission estimate') }}</span><b class="mono">{{ number_format($commissionEarned, 2) }}</b></div>
                <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Seller rating') }}</span><b class="mono">{{ number_format($averageRating, 1) }}</b></div>
                <p class="muted sm">{{ __('These figures come from existing order and product records. Product availability controls will be connected separately.') }}</p>
            </div>
        </section>
        <aside class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Quick actions') }}</h2></div>
            <div class="card-b stack">
                <a class="btn btn-p" href="{{ route('seller.orders.index') }}">{{ __('Review orders') }}</a>
                <a class="btn" href="{{ route('seller.markets.index') }}">{{ __('My markets') }}</a>
                <a class="btn" href="{{ route('seller.products.index') }}">{{ __('Product catalog') }}</a>
                <a class="btn" href="{{ route('seller.workers.index') }}">{{ __('Market workers') }}</a>
            </div>
        </aside>
    </div>
</div>
