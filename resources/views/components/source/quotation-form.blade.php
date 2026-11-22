@props([
    'request',
    'mode' => 'create',
    'currencyCode' => 'USD',
    'canChooseCurrency' => false,
    'cancelUrl' => null,
])

@php
    $isEdit = $mode === 'edit';
    $currencyOptions = ['AZN', 'USD', 'EUR', 'GBP', 'TRY', 'JPY', 'CNY'];
@endphp
<div class="stack source-quotation-form"
    x-data="{
        step: 1,
        lines: $wire.entangle('items'),
        validUntil: $wire.entangle('valid_until'),
        notes: $wire.entangle('notes'),
        terms: $wire.entangle('terms_conditions'),
        currency: @if($canChooseCurrency) $wire.entangle('currency') @else @js($currencyCode) @endif,
        positive(value) { return Number.isFinite(Number(value)) && Number(value) > 0; },
        money(value) { return Number(value || 0).toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); },
        total(line) {
            const quantity = Number(line.quantity);
            const price = Number(line.unit_price);
            const tax = Number(line.tax_rate || 0);
            return Number.isFinite(quantity) && Number.isFinite(price) && Number.isFinite(tax)
                && quantity > 0 && price >= 0 && tax >= 0 && tax <= 100
                ? quantity * price * (1 + tax / 100) : 0;
        },
        grandTotal() { return Object.values(this.lines || {}).reduce((total, line) => total + this.total(line), 0); },
        ready() {
            return Object.values(this.lines || {}).length > 0 &&
                Object.values(this.lines || {}).every(line => this.positive(line.quantity)
                    && this.positive(line.unit_price)
                    && Number(line.tax_rate || 0) >= 0 && Number(line.tax_rate || 0) <= 100) &&
                Boolean(this.validUntil) && this.grandTotal() > 0;
        },
        advance() {
            if (!this.ready()) { this.$refs.quoteForm.reportValidity(); return; }
            this.step = 2;
            this.$nextTick(() => this.$refs.reviewHeading?.focus());
        }
    }">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ $isEdit ? __('Update quotation') : __('Prepare quotation') }}</h1>
            <p class="ph-d">{{ __('RFQ') }} #{{ $request->id }} · {{ $request->title }}</p>
        </div>
        <div class="ph-a">
            <div class="seg" role="group" aria-label="{{ __('Quotation steps') }}">
                <button type="button" :class="{on:step===1}" x-on:click="step=1" :aria-current="step===1?'step':null">1 · {{ __('Prices & terms') }}</button>
                <button type="button" :class="{on:step===2}" x-on:click="advance()" :aria-current="step===2?'step':null">2 · {{ __('Review & submit') }}</button>
            </div>
        </div>
    </header>

    <div class="note">{{ __('Prices and totals update instantly without waiting for a server request. Review your quotation before submitting it.') }}</div>

    <form x-ref="quoteForm" wire:submit="save" x-on:submit="if(step!==2 || !ready()){ $event.preventDefault(); advance(); }">
        <div class="grid g-2-1">
            <div class="stack">
                <div x-show="step===1" class="stack">
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Itemised prices') }}</h2><span class="card-s">{{ count($items) }} {{ __('items') }}</span></div>
                        <div class="tbl-wrap">
                            <table class="tbl source-quotation-lines">
                                <thead><tr><th scope="col">{{ __('Item') }}</th><th scope="col" class="num">{{ __('Quantity') }}</th>
                                    <th scope="col">{{ __('Unit price') }}</th><th scope="col">{{ __('Tax (%)') }}</th>
                                    <th scope="col" class="num">{{ __('Line total') }}</th></tr></thead>
                                <tbody>
                                @foreach($items as $key => $item)
                                    <tr wire:key="quote-edit-line-{{ $key }}">
                                        <td><strong>{{ $item['description'] ?: __('Requested item') }}</strong>
                                            <div class="t2">{{ __('RFQ item') }} #{{ $item['request_item_id'] ?? $key }}</div>
                                            @error('items.'.$key.'.unit_price')<span class="err">{{ $message }}</span>@enderror
                                        </td>
                                        <td class="mono num">
                                            {{ number_format((float) ($item['quantity'] ?? 0), 2) }}
                                            <input type="hidden" x-model="lines[@js((string) $key)].quantity" />
                                        </td>
                                        <td><label class="sr" for="quote-unit-{{ $key }}">{{ __('Unit price for') }} {{ $item['description'] }}</label>
                                            <input id="quote-unit-{{ $key }}" type="number" min="0.01" step="0.01"
                                                class="input mono num" x-model="lines[@js((string) $key)].unit_price"
                                                required />
                                        </td>
                                        <td><label class="sr" for="quote-tax-{{ $key }}">{{ __('Tax percentage for') }} {{ $item['description'] }}</label>
                                            <input id="quote-tax-{{ $key }}" type="number" min="0" max="100" step="0.01"
                                                class="input mono num" x-model="lines[@js((string) $key)].tax_rate" />
                                        </td>
                                        <td class="mono num"><span x-text="currency + ' ' + money(total(lines[@js((string) $key)]))"></span></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Delivery & terms') }}</h2></div>
                        <div class="card-b fgrid">
                            <div class="field">
                                <label for="quote-valid-until">{{ __('Valid until') }}</label>
                                <input id="quote-valid-until" type="date" class="input" x-model="validUntil"
                                    min="{{ now()->addDay()->toDateString() }}" required />
                                @error('valid_until')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            @if($canChooseCurrency)
                                <div class="field"><label for="quote-currency">{{ __('Currency') }}</label>
                                    <select id="quote-currency" class="select" x-model="currency" required>
                                        @foreach($currencyOptions as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach
                                    </select>
                                    @error('currency')<span class="err">{{ $message }}</span>@enderror
                                </div>
                            @else
                                <div class="field"><label>{{ __('Currency') }}</label><strong class="input source-currency-readonly">{{ $currencyCode }}</strong></div>
                            @endif
                            <div class="field"><label for="quote-notes">{{ __('Notes to buyer') }}</label>
                                <textarea id="quote-notes" class="textarea" rows="3" x-model="notes"></textarea>
                                @error('notes')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field"><label for="quote-terms">{{ __('Terms & conditions') }}</label>
                                <textarea id="quote-terms" class="textarea" rows="3" x-model="terms"></textarea>
                                @error('terms_conditions')<span class="err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </section>
                    <div class="row" style="justify-content:flex-end">
                        <button type="button" class="btn btn-p" x-on:click="advance()" :disabled="!ready()">{{ __('Review quotation') }}</button>
                    </div>
                </div>

                <div class="stack" x-show="step===2" x-cloak>
                    <section class="card">
                        <div class="card-h"><h2 class="card-t" tabindex="-1" x-ref="reviewHeading">{{ __('Review before submission') }}</h2>
                            <span class="sp"></span><button class="btn btn-sm" type="button" x-on:click="step=1">{{ __('Edit prices') }}</button></div>
                        <div class="tbl-wrap">
                            <table class="tbl compact">
                                <thead><tr><th>{{ __('Item') }}</th><th class="num">{{ __('Quantity') }}</th>
                                    <th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Total incl. tax') }}</th></tr></thead>
                                <tbody>
                                @foreach($items as $key => $item)
                                    <tr><td>{{ $item['description'] }}</td>
                                        <td class="num mono">{{ number_format((float) $item['quantity'],2) }}</td>
                                        <td class="num mono" x-text="money(lines[@js((string) $key)].unit_price)"></td>
                                        <td class="num mono" x-text="money(total(lines[@js((string) $key)]))"></td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Quotation summary') }}</h2></div>
                        <div class="card-b"><dl class="kv">
                            <dt>{{ __('Valid until') }}</dt><dd x-text="validUntil"></dd>
                            <dt>{{ __('Currency') }}</dt><dd x-text="currency"></dd>
                            <dt>{{ __('Total incl. tax') }}</dt><dd class="mono b" x-text="money(grandTotal())"></dd>
                            <dt>{{ __('Notes') }}</dt><dd x-text="notes || '—'"></dd>
                            <dt>{{ __('Terms') }}</dt><dd x-text="terms || '—'"></dd>
                        </dl></div>
                    </section>
                    <div class="row wrap" style="justify-content:space-between">
                        <button type="button" class="btn" x-on:click="step=1">{{ __('Back to edit') }}</button>
                        <button type="submit" class="btn btn-p" :disabled="!ready()" wire:loading.attr="disabled"
                            wire:target="save">{{ $isEdit ? __('Update quotation') : __('Submit quotation') }}</button>
                    </div>
                </div>
            </div>

            <aside class="stack">
                <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Quotation totals') }}</h2></div>
                    <div class="card-b stack">
                        <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Items') }}</span><b>{{ count($items) }}</b></div>
                        <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Total incl. tax') }}</span>
                            <b class="mono" style="font-size:20px" x-text="currency + ' ' + money(grandTotal())"></b></div>
                        <div class="prog"><i :style="'width:' + (ready() ? 100 : 0) + '%'"></i></div>
                        <span class="sm muted" x-text="ready() ? @js(__('Ready for review')) : @js(__('Enter positive prices for all items'))"></span>
                    </div>
                </section>
                <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Request summary') }}</h2></div>
                    <div class="card-b"><dl class="kv">
                        <dt>{{ __('Reference') }}</dt><dd class="mono">RFQ #{{ $request->id }}</dd>
                        <dt>{{ __('Buyer') }}</dt><dd>{{ $request->buyer?->name ?? '—' }}</dd>
                        <dt>{{ __('Deadline') }}</dt><dd>{{ $request->deadline?->format('d M Y') ?? '—' }}</dd>
                        <dt>{{ __('Request') }}</dt><dd>{{ $request->title }}</dd>
                    </dl></div>
                </section>
                @if($errors->any())<div class="note bad" role="alert">{{ __('Please correct the errors before submitting.') }}</div>@endif
            </aside>
        </div>
    </form>
    @if($cancelUrl)<a class="link sm" href="{{ $cancelUrl }}" wire:navigate>{{ __('Cancel and return') }}</a>@endif
</div>
