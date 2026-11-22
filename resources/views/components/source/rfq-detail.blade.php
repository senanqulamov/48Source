@props(['request','buyer' => false])

@php
    $tone = match(strtolower($request->status)) {
        'open'=>'b-info', 'closed'=>'b-warn', 'awarded'=>'b-ok', 'cancelled'=>'b-bad', default=>'',
    };
    $flow = ['draft'=>'Draft', 'open'=>'Open', 'closed'=>'Closed', 'awarded'=>'Awarded'];
    $currentIndex = array_search($request->status, array_keys($flow), true);
    $next = match ($request->status) {
        'draft' => __('Publish the RFQ and invite suppliers'),
        'open' => __('Collect and review supplier quotations'),
        'closed' => __('Evaluate quotes and record an award decision'),
        'awarded' => __('Proceed to purchase-order processing'),
        'cancelled' => __('This request was cancelled'),
        default => __('Review the request status'),
    };
    $backRoute = $buyer ? 'buyer.rfq.index' : 'rfq.index';
@endphp

<div class="stack" x-data="{ tab: 'overview' }">
    <header class="ph">
        <div>
            <div class="row sm muted" style="margin-bottom:8px"><a href="{{ route($backRoute) }}" class="link">{{ __('RFQs') }}</a><span>/</span><span class="mono">#{{ $request->id }}</span></div>
            <h1 class="ph-t">{{ $request->title }}</h1>
            <p class="ph-d">{{ __('RFQ') }} #{{ $request->id }} · {{ $request->buyer?->name ?? __('Buyer') }}</p>
        </div>
        <div class="ph-a">
            <span class="badge {{ $tone }}">{{ ucfirst($request->status) }}</span>
            @can('edit_rfqs')
                <button type="button" class="btn" @if($buyer) wire:click="$dispatch('buyer::load::rfq', { rfq: '{{ $request->id }}' })" @else wire:click="$dispatch('load::rfq', { rfq: '{{ $request->id }}' })" @endif>{{ __('Edit request') }}</button>
            @endcan
        </div>
    </header>

    <div class="life">
        <ol>
            @foreach($flow as $step => $label)
                @php
                    $index = array_search($step, array_keys($flow), true);
                    $cls = $currentIndex === $index ? 'now' : ($currentIndex !== false && $index < $currentIndex ? 'done' : '');
                @endphp
                <li class="{{ $cls }}"><span class="d">{{ $index + 1 }}</span><span>{{ $label }}</span></li>
            @endforeach
        </ol>
        <div class="nx"><strong>{{ __('Next step') }}:</strong> {{ $next }}</div>
    </div>

    <div class="facts">
        <div class="fact"><span>{{ __('Status') }}</span><b>{{ ucfirst($request->status) }}</b></div>
        <div class="fact"><span>{{ __('Deadline') }}</span><b>{{ $request->deadline?->format('d M Y') ?? '—' }}</b></div>
        <div class="fact"><span>{{ __('Request type') }}</span><b>{{ $request->request_type === 'public' ? __('Public tender') : __('Internal request') }}</b></div>
        <div class="fact"><span>{{ __('Items') }}</span><b>{{ $request->items->count() }}</b></div>
        <div class="fact"><span>{{ __('Quotations') }}</span><b>{{ $request->quotes->count() }}</b></div>
    </div>

    <div class="tabs" role="tablist" aria-label="RFQ detail sections">
        @foreach(['overview'=>__('Overview'),'items'=>__('Items'),'suppliers'=>__('Suppliers'),'quotes'=>__('Quotation comparison')] as $key => $label)
            <button type="button" class="tab" :class="{ on: tab==='{{ $key }}' }" :aria-selected="(tab==='{{ $key }}').toString()" x-on:click="tab='{{ $key }}'" role="tab">{{ $label }}</button>
        @endforeach
    </div>

    <section x-show="tab==='overview'" aria-label="RFQ overview">
        <div class="grid g-2-1">
            <div class="stack">
                <div class="card">
                    <div class="card-h"><h2 class="card-t">{{ __('Request details') }}</h2></div>
                    <div class="card-b">
                        <dl class="kv">
                            <dt>{{ __('Buyer') }}</dt><dd>{{ $request->buyer?->name ?? '—' }}</dd>
                            <dt>{{ __('Created') }}</dt><dd>{{ $request->created_at?->format('d M Y, H:i') }}</dd>
                            <dt>{{ __('Deadline') }}</dt><dd>{{ $request->deadline?->format('d M Y') ?? '—' }}</dd>
                            <dt>{{ __('Delivery location') }}</dt><dd>{{ $request->delivery_location ?: '—' }}</dd>
                            <dt>{{ __('Field assessment') }}</dt><dd>{{ $request->requires_field_assessment ? __('Required') : __('Not required') }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="card">
                    <div class="card-h"><h2 class="card-t">{{ __('Description') }}</h2></div>
                    <div class="card-b" style="white-space:pre-line">{{ $request->description ?: __('No description provided.') }}</div>
                </div>
            </div>
            <div class="stack">
                <div class="card">
                    <div class="card-h"><h2 class="card-t">{{ __('Procurement progress') }}</h2></div>
                    <div class="card-b">
                        <div class="row"><span class="muted">{{ __('Invited suppliers') }}</span><span class="sp"></span><b class="mono">{{ $request->supplierInvitations->count() }}</b></div>
                        <div class="row" style="margin-top:12px"><span class="muted">{{ __('Submitted quotations') }}</span><span class="sp"></span><b class="mono">{{ $request->quotes->where('status','submitted')->count() }}</b></div>
                        <div class="row" style="margin-top:12px"><span class="muted">{{ __('Items requested') }}</span><span class="sp"></span><b class="mono">{{ $request->items->count() }}</b></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section x-show="tab==='items'" x-cloak aria-label="Requested items">
        <div class="card"><div class="card-h"><h2 class="card-t">{{ __('Requested items') }}</h2></div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>#</th><th>{{ __('Item') }}</th><th class="num">{{ __('Quantity') }}</th><th>{{ __('Specifications') }}</th></tr></thead>
                <tbody>
                @forelse($request->items as $i => $item)
                    <tr><td class="mono">{{ $i+1 }}</td><td class="t1">{{ $item->product_name }}</td><td class="num mono">{{ $item->quantity }}</td><td class="muted">{{ $item->specifications ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">{{ __('No items on this request.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

    <section x-show="tab==='suppliers'" x-cloak aria-label="Supplier invitations">
        <div class="card"><div class="card-h"><h2 class="card-t">{{ __('Invited suppliers') }}</h2></div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Status') }}</th><th>{{ __('Invited') }}</th><th>{{ __('Responded') }}</th></tr></thead>
                <tbody>
                @forelse($request->supplierInvitations as $inv)
                    <tr><td class="t1">{{ $inv->supplier?->company_name ?: $inv->supplier?->name ?: __('Unknown') }}</td><td><span class="badge">{{ ucfirst($inv->status) }}</span></td><td class="muted">{{ $inv->sent_at?->format('d M Y') ?? '—' }}</td><td class="muted">{{ $inv->responded_at?->format('d M Y') ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">{{ __('No supplier invitations yet.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

    <section x-show="tab==='quotes'" x-cloak aria-label="Quotation comparison">
        <x-source.quote-comparison :request="$request" />
    </section>
</div>
