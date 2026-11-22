<div class="stack">
    <header class="ph"><div>
        <div class="row muted sm"><a class="link" href="{{ route('procurement.orders.index') }}">{{ __('Procurement POs') }}</a><span>/</span><span class="mono">#{{ $purchaseOrder->id }}</span></div>
        <h1 class="ph-t">{{ __('Procurement PO') }} #{{ str_pad($purchaseOrder->id, 6, '0', STR_PAD_LEFT) }}</h1>
        <p class="ph-d">{{ __('Awarded from RFQ') }} #{{ $purchaseOrder->request_id }} · {{ $purchaseOrder->supplier?->company_name ?: $purchaseOrder->supplier?->name }}</p>
    </div><div class="ph-a">
        <span class="badge b-info">{{ ucfirst(str_replace('_',' ',$purchaseOrder->status)) }}</span>
        @if($canAdvance)
            <button class="btn btn-p" type="button" wire:click="advance" wire:confirm="{{ __('Move this procurement PO to the next status?') }}" wire:loading.attr="disabled">
                {{ __('Mark') }} {{ ucfirst(str_replace('_', ' ', $next)) }}
            </button>
        @endif
    </div></header>
    <div class="facts">
        <div class="fact"><span>{{ __('Status') }}</span><b>{{ ucfirst(str_replace('_',' ',$purchaseOrder->status)) }}</b></div>
        <div class="fact"><span>{{ __('Supplier') }}</span><b>{{ $purchaseOrder->supplier?->name ?? '—' }}</b></div>
        <div class="fact"><span>{{ __('Currency') }}</span><b class="mono">{{ $purchaseOrder->currency }}</b></div>
        <div class="fact"><span>{{ __('Total') }}</span><b class="mono">{{ number_format((float)$purchaseOrder->total_amount,2) }}</b></div>
    </div>
    <div class="grid g-2-1"><section class="card"><div class="card-h"><h2 class="card-t">{{ __('Awarded line items') }}</h2></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Item') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Tax') }}</th><th class="num">{{ __('Total') }}</th></tr></thead>
            <tbody>@foreach($purchaseOrder->lines as $line)
                <tr><td class="t1">{{ $line->description }}</td><td class="num mono">{{ $line->quantity }}</td>
                    <td class="num mono">{{ $line->unit_price }}</td><td class="num mono">{{ $line->tax_rate }}%</td>
                    <td class="num mono">{{ $line->line_total }}</td></tr>
            @endforeach</tbody>
        </table></div></section>
        <aside class="stack">
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Approval & status history') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Awarded quotation') }}</dt><dd class="mono">#{{ $purchaseOrder->quote_id }}</dd>
                    <dt>{{ __('Justification') }}</dt><dd>{{ $purchaseOrder->award_justification ?: __('Lowest eligible bid') }}</dd>
                    @foreach(['issued_at'=>'Issued','acknowledged_at'=>'Acknowledged','in_transit_at'=>'In transit','delivered_at'=>'Delivered','invoiced_at'=>'Invoiced'] as $field => $label)
                        <dt>{{ __($label) }}</dt><dd class="mono sm">{{ $purchaseOrder->{$field}?->format('d M Y H:i') ?? '—' }}</dd>
                    @endforeach
                </dl></div>
            </section>
        </aside>
    </div>
</div>