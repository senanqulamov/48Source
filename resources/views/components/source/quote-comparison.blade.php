@props(['request'])

@php
    $quotes = $request->quotes->filter(fn($q) => in_array($q->status, ['submitted', 'accepted', 'rejected'], true))->values();
    $requestItems = $request->items;
    $unitLowest = [];
    $completeTotals = [];
    foreach ($requestItems as $item) {
        $prices = $quotes->map(fn($q) => $q->items->firstWhere('request_item_id', $item->id)?->unit_price)
            ->filter(fn($v) => is_numeric($v) && (float) $v > 0)->map(fn($v) => (float) $v);
        $unitLowest[$item->id] = $prices->isEmpty() ? null : $prices->min();
    }
    foreach ($quotes as $quote) {
        $sum = 0;
        $complete = true;
        foreach ($requestItems as $item) {
            $line = $quote->items->firstWhere('request_item_id', $item->id);
            if (!$line || $line->unit_price === null || (float) $line->unit_price <= 0) {
                $complete = false;
                continue;
            }
            $sum += (float) $line->unit_price * (float) $item->quantity;
        }
        if ($complete) {
            $completeTotals[$quote->id] = $sum;
        }
    }
    $lowestComplete = $completeTotals ? array_search(min($completeTotals), $completeTotals, true) : null;
@endphp

<div class="stack" x-data="{ hidden: [] }">
    <div class="verdicts">
        <div class="verdict best"><h4>{{ __('Best value') }}</h4><b>{{ __('Awaiting verified assurance and performance metrics') }}</b><p class="muted sm">{{ __('Weighted evaluation cannot run on placeholder scores.') }}</p></div>
        <div class="verdict"><h4>{{ __('Lowest complete quotation') }}</h4>
            <b>@if($lowestComplete){{ $quotes->firstWhere('id', $lowestComplete)?->supplier?->company_name ?: $quotes->firstWhere('id', $lowestComplete)?->supplier?->name }}@else — @endif</b>
            <p class="muted sm">{{ $lowestComplete ? number_format($completeTotals[$lowestComplete], 2) . ' (excl. tax)' : __('No complete quotation') }}</p>
        </div>
        <div class="verdict"><h4>{{ __('Fastest delivery') }}</h4><b>—</b><p class="muted sm">{{ __('Delivery lead time is not stored on existing quotes.') }}</p></div>
    </div>

    @if($quotes->isEmpty())
        <div class="card"><div class="empty"><h3>{{ __('No submitted quotations') }}</h3><p>{{ __('The comparison will appear after suppliers submit quotations.') }}</p></div></div>
    @else
        <div class="card">
            <div class="card-h">
                <h2 class="card-t">{{ __('Quotation comparison') }}</h2>
                <span class="card-s">{{ $quotes->count() }} {{ __('submitted quotations') }}</span>
            </div>
            <div class="toolbar">
                <span class="sm muted">{{ __('Show or hide suppliers') }}</span>
                @foreach($quotes as $quote)
                    <button type="button" class="chip" :class="{ off:hidden.includes({{ $quote->id }}) }" x-on:click="hidden = hidden.includes({{ $quote->id }}) ? hidden.filter(i=>i!=={{ $quote->id }}) : [...hidden, {{ $quote->id }}]">
                        {{ $quote->supplier?->company_name ?: $quote->supplier?->name ?: __('Supplier') }}
                    </button>
                @endforeach
            </div>
            <div class="cmp-wrap">
                <table class="cmp">
                    <thead><tr>
                        <th class="rh" scope="col">{{ __('Requirement') }}</th>
                        @foreach($quotes as $quote)
                            <th scope="col" x-show="!hidden.includes({{ $quote->id }})">
                                <div class="sname">{{ $quote->supplier?->company_name ?: $quote->supplier?->name ?: __('Unknown supplier') }}</div>
                                <div class="muted sm">{{ __('Quote') }} #{{ $quote->id }} · {{ ucfirst($quote->status) }}</div>
                                <div class="muted sm">{{ $quote->currency ?: 'Currency not specified' }}</div>
                            </th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        <tr class="sec"><td colspan="{{ $quotes->count() + 1 }}">{{ __('Quoted line items · unit prices') }}</td></tr>
                        @foreach($requestItems as $item)
                            <tr>
                                <th class="rh" scope="row"><div class="b">{{ $item->product_name }}</div><div class="muted sm">{{ $item->quantity }} {{ __('units') }}</div></th>
                                @foreach($quotes as $quote)
                                    @php
                                        $line = $quote->items->firstWhere('request_item_id', $item->id);
                                        $value = $line && $line->unit_price !== null && (float) $line->unit_price > 0 ? (float) $line->unit_price : null;
                                        $low = $unitLowest[$item->id];
                                    @endphp
                                    <td x-show="!hidden.includes({{ $quote->id }})" class="{{ $value !== null && $low !== null && $value === $low ? 'best' : '' }}">
                                        @if($value === null)
                                            <span class="nq">{{ __('Not quoted') }}</span>
                                        @else
                                            <span class="price">{{ number_format($value, 2) }}</span>
                                            @if($low !== null && abs($value - $low) < 0.00001)
                                                <span class="lowtag">{{ __('Lowest') }}</span>
                                            @elseif($low !== null)
                                                <span class="delta">+{{ number_format(($value / $low - 1) * 100, 1) }}%</span>
                                            @endif
                                            <div class="tot">{{ number_format($value * (float) $item->quantity, 2) }} {{ __('line total') }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="sec"><td colspan="{{ $quotes->count() + 1 }}">{{ __('Summary') }}</td></tr>
                        <tr>
                            <th class="rh" scope="row">{{ __('Quoted subtotal (excl. tax)') }}</th>
                            @foreach($quotes as $quote)
                                <td x-show="!hidden.includes({{ $quote->id }})">
                                    @if(isset($completeTotals[$quote->id]))
                                        <b class="big">{{ number_format($completeTotals[$quote->id], 2) }}</b>
                                        @if($quote->id === $lowestComplete)<div class="lowtag">{{ __('Lowest complete quote') }}</div>@endif
                                    @else
                                        <span class="badge b-warn">{{ __('Partial quotation') }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="awardbar">
                <span class="muted sm">{{ __('Awards remain disabled until scoring, compliance and audited justification are connected to the backend.') }}</span>
            </div>
        </div>
    @endif
</div>
