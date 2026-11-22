<div class="stack">
    <header class="ph">
        <div>
            <p class="ph-d"><a class="link" href="{{ route('supplier.rfq.index') }}">{{ __('Available RFQs') }}</a> / <span class="mono">#{{ $request->id }}</span></p>
            <h1 class="ph-t">{{ $request->title }}</h1>
            <p class="ph-d">{{ $request->buyer?->name ?? __('Buyer') }} · {{ __('Quotation deadline') }} {{ $request->deadline?->format('d M Y') ?? '—' }}</p>
        </div>
        <div class="ph-a">
            <span class="badge {{ $request->status === 'open' ? 'b-info' : '' }}">{{ ucfirst($request->status) }}</span>
            @if($canQuote)
                @if($request->requires_field_assessment && !$assessmentComplete)
                    <a href="{{ route('supplier.field-assessment.create', $request) }}" class="btn btn-p">{{ __('Complete field assessment') }}</a>
                @else
                    <a href="{{ route('supplier.rfq.quote', $request) }}" class="btn btn-p">{{ __('Prepare quotation') }}</a>
                @endif
            @endif
        </div>
    </header>
    <div class="facts">
        <div class="fact"><span>{{ __('Reference') }}</span><b class="mono">#{{ $request->id }}</b></div>
        <div class="fact"><span>{{ __('Buyer') }}</span><b>{{ $request->buyer?->name ?? '—' }}</b></div>
        <div class="fact"><span>{{ __('Deadline') }}</span><b>{{ $request->deadline?->format('d M Y') ?? '—' }}</b></div>
        <div class="fact"><span>{{ __('Items') }}</span><b>{{ $request->items->count() }}</b></div>
        <div class="fact"><span>{{ __('Request type') }}</span><b>{{ $request->request_type === 'public' ? __('Public tender') : __('Invited request') }}</b></div>
    </div>
    <div class="grid g-2-1">
        <div class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Description & scope') }}</h2></div>
                <div class="card-b" style="white-space:pre-line">{{ $request->description ?: __('No description was provided.') }}</div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Requested items') }}</h2><span class="card-s">{{ $request->items->count() }} {{ __('items') }}</span></div>
                <div class="tbl-wrap"><table class="tbl">
                    <thead><tr><th>#</th><th>{{ __('Product or service') }}</th><th class="num">{{ __('Quantity') }}</th><th>{{ __('Specifications') }}</th></tr></thead>
                    <tbody>
                    @foreach($request->items as $item)
                        <tr><td class="mono">{{ $loop->iteration }}</td><td class="t1">{{ $item->product_name ?: $item->product?->name ?: '—' }}</td><td class="num mono">{{ $item->quantity }}</td><td class="sm muted">{{ $item->specifications ?: '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>
        </div>
        <aside class="stack">
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Your quotation') }}</h2></div>
                <div class="card-b stack">
                    @if($supplierQuote)
                        <div class="note">{{ __('You have already submitted a quotation for this RFQ.') }}</div>
                        <div class="row"><span class="muted">{{ __('Status') }}</span><span class="sp"></span><span class="badge">{{ ucfirst($supplierQuote->status) }}</span></div>
                    @elseif($canQuote)
                        <div class="note">{{ __('This request is accepting quotations.') }}</div>
                        @if($request->requires_field_assessment && !$assessmentComplete)
                            <p class="muted sm">{{ __('Submit a field assessment before quoting.') }}</p>
                            <a class="btn btn-p" href="{{ route('supplier.field-assessment.create', $request) }}">{{ __('Start assessment') }}</a>
                        @else
                            <a class="btn btn-p" href="{{ route('supplier.rfq.quote', $request) }}">{{ __('Submit a quote') }}</a>
                        @endif
                    @else
                        <div class="note">{{ __('This RFQ is not currently available for a new quotation.') }}</div>
                    @endif
                </div>
            </section>
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Delivery requirements') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Location') }}</dt><dd>{{ $request->delivery_location ?: '—' }}</dd>
                    <dt>{{ __('Address') }}</dt><dd>{{ $request->delivery_address ?: '—' }}</dd>
                    <dt>{{ __('Instructions') }}</dt><dd>{{ $request->special_instructions ?: '—' }}</dd>
                    <dt>{{ __('Field assessment') }}</dt><dd>{{ $request->requires_field_assessment ? ($assessmentComplete ? __('Completed') : __('Required')) : __('Not required') }}</dd>
                </dl></div>
            </section>
        </aside>
    </div>
</div>
