<div class="stack" x-data="{step:'prices'}">
    <header class="ph">
        <div><p class="ph-d"><a class="link" href="{{ route('supplier.rfq.show', $request) }}">{{ __('RFQ') }} #{{ $request->id }}</a> / {{ __('Quote') }}</p>
            <h1 class="ph-t" x-text="step === 'prices' ? @js(__('Prepare quotation')) : @js(__('Review quotation'))"></h1>
            <p class="ph-d">{{ $request->title }}</p></div>
        <div class="ph-a"><div class="seg" role="group" aria-label="{{ __('Progress') }}">
            <button type="button" :class="{on:step==='prices'}" x-on:click="step='prices'">1 · {{ __('Prices & terms') }}</button>
            <button type="button" :class="{on:step==='review'}" x-on:click="step='review'">2 · {{ __('Review & submit') }}</button>
        </div></div>
    </header>
    <form wire:submit="save">
        <div class="grid g-2-1">
            <div class="stack">
                <div class="stack" x-show="step==='prices'">
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Itemised prices') }}</h2><span class="card-s">{{ $request->items->count() }} {{ __('items') }}</span></div>
                        <div class="tbl-wrap"><table class="tbl">
                            <thead><tr><th>#</th><th>{{ __('Product / service') }}</th><th class="num">{{ __('Quantity') }}</th><th>{{ __('Unit price') }}</th><th>{{ __('Tax (%)') }}</th><th class="num">{{ __('Line total') }}</th></tr></thead>
                            <tbody>
                                @foreach($request->items as $item)
                                    @php($line = $items[$item->id] ?? [])
                                    <tr wire:key="supplier-quote-line-{{ $item->id }}">
                                        <td class="mono faint">{{ $loop->iteration }}</td>
                                        <td><div class="t1">{{ $item->product_name ?: __('Item') }}</div><div class="t2">{{ $item->specifications }}</div></td>
                                        <td class="num mono">{{ $item->quantity }}</td>
                                        <td><input type="number" class="input mono num" min="0" step="0.01" inputmode="decimal" wire:model.live.debounce.300ms="items.{{ $item->id }}.unit_price" aria-label="{{ __('Unit price for') }} {{ $item->product_name }}" required>
                                            @error("items.$item->id.unit_price")<span class="err">{{ $message }}</span>@enderror</td>
                                        <td><input type="number" class="input mono num" min="0" max="100" step="0.01" wire:model.live.debounce.300ms="items.{{ $item->id }}.tax_rate" aria-label="{{ __('Tax rate for') }} {{ $item->product_name }}"></td>
                                        <td class="num mono">{{ number_format((float)($line['quantity'] ?? 0) * (float)($line['unit_price'] ?? 0) * (1 + (float)($line['tax_rate'] ?? 0)/100),2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table></div>
                        <div class="help" style="padding:12px 16px">{{ __('The current application requires a price for every line. Partial quotations are not yet enabled.') }}</div>
                    </section>
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Delivery and commercial terms') }}</h2></div>
                        <div class="card-b fgrid">
                            <div class="field">
                                <label for="supplier-quote-valid">{{ __('Valid until') }} *</label>
                                <input id="supplier-quote-valid" type="date" class="input" wire:model.live="valid_until" required min="{{ now()->addDay()->format('Y-m-d') }}">
                            </div>
                            <div class="field">
                                <label for="supplier-quote-currency">{{ __('Currency') }}</label>
                                <select id="supplier-quote-currency" class="select" wire:model.live="currency">
                                    <option value="USD">USD</option><option value="EUR">EUR</option><option value="AZN">AZN</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="supplier-quote-terms">{{ __('Terms and conditions') }}</label>
                                <textarea id="supplier-quote-terms" class="textarea" rows="3" wire:model.live="terms_conditions"></textarea>
                            </div>
                            <div class="field">
                                <label for="supplier-quote-notes">{{ __('Notes') }}</label>
                                <textarea id="supplier-quote-notes" class="textarea" rows="3" wire:model.live="notes"></textarea>
                            </div>
                        </div>
                    </section>
                    <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="button" x-on:click="step='review'">{{ __('Review quotation') }}</button></div>
                </div>
                <div class="stack" x-show="step==='review'" x-cloak>
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Review line prices') }}</h2><span class="sp"></span><button type="button" class="link sm" x-on:click="step='prices'">{{ __('Edit') }}</button></div>
                        <div class="tbl-wrap"><table class="tbl compact">
                            <thead><tr><th>{{ __('Item') }}</th><th class="num">{{ __('Quantity') }}</th><th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Total including tax') }}</th></tr></thead>
                            <tbody>@foreach($request->items as $item)
                                @php($line = $items[$item->id] ?? [])
                                <tr><td>{{ $item->product_name ?: __('Item') }}</td><td class="num mono">{{ $item->quantity }}</td>
                                    <td class="num mono">{{ number_format((float)($line['unit_price'] ?? 0),2) }}</td>
                                    <td class="num mono">{{ number_format((float)($line['quantity'] ?? 0) * (float)($line['unit_price'] ?? 0) * (1 + (float)($line['tax_rate'] ?? 0)/100),2) }}</td></tr>
                            @endforeach</tbody>
                        </table></div>
                    </section>
                    <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Commercial summary') }}</h2></div>
                        <div class="card-b"><dl class="kv">
                            <dt>{{ __('Total incl. tax') }}</dt><dd class="mono b">{{ $currency }} {{ number_format($this->calculateTotal(),2) }}</dd>
                            <dt>{{ __('Valid until') }}</dt><dd>{{ $valid_until ?: '—' }}</dd>
                            <dt>{{ __('Terms') }}</dt><dd>{{ $terms_conditions ?: '—' }}</dd>
                            <dt>{{ __('Notes') }}</dt><dd>{{ $notes ?: '—' }}</dd>
                        </dl></div>
                    </section>
                    <div class="row" style="justify-content:space-between">
                        <button class="btn" type="button" x-on:click="step='prices'">{{ __('Back') }}</button>
                        <button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Submit quotation') }}</button>
                    </div>
                </div>
            </div>
            <aside class="stack">
                <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Your total') }}</h2></div>
                    <div class="card-b"><span class="strip-v">{{ $currency }} {{ number_format($this->calculateTotal(),2) }}</span><p class="muted sm">{{ __('Includes line taxes') }}</p></div>
                </section>
                <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Request summary') }}</h2></div>
                    <div class="card-b"><dl class="kv"><dt>{{ __('RFQ') }}</dt><dd class="mono">#{{ $request->id }}</dd>
                        <dt>{{ __('Buyer') }}</dt><dd>{{ $request->buyer?->name ?? '—' }}</dd>
                        <dt>{{ __('Deadline') }}</dt><dd>{{ $request->deadline?->format('d M Y') ?? '—' }}</dd>
                        <dt>{{ __('Items') }}</dt><dd>{{ $request->items->count() }}</dd></dl>
                    </div>
                </section>
                @if($errors->any())<div class="note bad" role="alert">{{ __('Please correct the quote details before submission.') }}</div>@endif
            </aside>
        </div>
    </form>
</div>
