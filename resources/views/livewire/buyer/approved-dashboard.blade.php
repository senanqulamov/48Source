<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('Buyer workspace') }}</h1>
            <p class="ph-d">{{ __('Manage your requests, supplier quotations and procurement progress.') }}</p>
        </div>
        <div class="ph-a"><a href="{{ route('buyer.rfq.index') }}" class="btn btn-p">{{ __('My RFQs') }}</a></div>
    </header>
    <div class="strip">
        <a class="strip-i" href="{{ route('buyer.rfq.index') }}"><span class="strip-l">{{ __('Open RFQs') }}</span><span class="strip-v">{{ $openRfqs }}</span><span class="strip-s">{{ __('Accepting supplier quotations') }}</span></a>
        <a class="strip-i" href="{{ route('buyer.rfq.index') }}"><span class="strip-l">{{ __('Pending quotations') }}</span><span class="strip-v">{{ $pendingQuotes }}</span><span class="strip-s">{{ __('Awaiting review') }}</span></a>
        <div class="strip-i"><span class="strip-l">{{ __('Awarded quotations') }}</span><span class="strip-v">{{ $awardedContracts }}</span><span class="strip-s">{{ __('Accepted supplier offers') }}</span></div>
        <div class="strip-i"><span class="strip-l">{{ __('Active suppliers') }}</span><span class="strip-v">{{ $activeSuppliers }}</span><span class="strip-s">{{ __('Suppliers quoting on your RFQs') }}</span></div>
    </div>
    <div class="grid g-2-1">
        <section class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Procurement at a glance') }}</h2></div>
            <div class="card-b stack">
                <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Completed marketplace orders') }}</span><b class="mono">{{ $completedOrders }}</b></div>
                <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Spend on completed orders') }}</span><b class="mono">{{ number_format($totalSpend,2) }}</b></div>
                <div class="row" style="justify-content:space-between"><span class="muted">{{ __('RFQs accepting quotations') }}</span><b class="mono">{{ $openRfqs }}</b></div>
                <p class="muted sm">{{ __('Procurement purchase orders will be reported separately after the new award workflow is implemented.') }}</p>
            </div>
        </section>
        <aside class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Needs your attention') }}</h2></div>
                <div class="card-b">
                    @if($pendingQuotes)
                        <div class="note warn">{{ __('You have supplier quotations awaiting review.') }}</div>
                        <a href="{{ route('buyer.rfq.index') }}" class="btn btn-p" style="margin-top:12px">{{ __('Review RFQs') }}</a>
                    @else
                        <div class="empty">{{ __('No new quotations need review.') }}</div>
                    @endif
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Quick access') }}</h2></div>
                <div class="card-b stack">
                    <a class="btn btn-p" href="{{ route('buyer.rfq.index') }}">{{ __('Requests for quotation') }}</a>
                    <a class="btn" href="{{ route('buyer.products.index') }}">{{ __('Browse products') }}</a>
                    <a class="btn" href="{{ route('buyer.markets.index') }}">{{ __('Browse markets') }}</a>
                </div>
            </section>
        </aside>
    </div>
</div>
