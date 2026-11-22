<div class="stack" x-data="{ step: 'prices' }">
    <header class="ph">
        <div>
            <h1 class="ph-t" x-text="step === 'prices' ? @js(__('Prepare quotation')) : @js(__('Review before submission'))"></h1>
            <p class="ph-d"><span class="mono">RFQ #{{ $invitation->request_id }}</span> · {{ $invitation->request?->title }}</p>
        </div>
        <div class="ph-a">
            <div class="seg" role="group" aria-label="{{ __('Quotation progress') }}">
                <button type="button" :class="{on:step==='prices'}" x-on:click="step='prices'">1 · {{ __('Prices & terms') }}</button>
                <button type="button" :class="{on:step==='review'}" x-on:click="step='review'">2 · {{ __('Review & submit') }}</button>
            </div>
        </div>
    </header>
    <div class="note warn">{{ __('Partial quotations, historical price hints, delivery lead-time scoring and persistent drafts require the new backend workflow and are not yet supported by this form. Every line currently requires a price.') }}</div>
    <form wire:submit="save">
        <div class="grid g-2-1">
            <div class="stack">
                <div x-show="step==='prices'" class="stack">
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Itemised prices') }}</h2><span class="card-s">{{ count($items) }} {{ __('items') }}</span></div>
                        <div class="tbl-wrap">
                            <table class="tbl">
                                <thead><tr><th>#</th><th>{{ __('Item') }}</th><th class="num">{{ __('Quantity') }}</th><th>{{ __('Unit price') }}</th><th>{{ __('Tax (%)') }}</th><th class="num">{{ __('Line total') }}</th></tr></thead>
                                <tbody>
                                @foreach($items as $index => $item)
                                    <tr wire:key="quote-line-{{ $index }}">
                                        <td class="mono faint">{{ $index+1 }}</td>
                                        <td>
                                            <label for="quote-price-{{ $index }}" class="t1">{{ $item['description'] ?: $invitation->request?->items->firstWhere('id', $item['request_item_id'])?->product_name ?: __('Item') }}</label>
                                            <input type="hidden" wire:model="items.{{ $index }}.description" />
                                        </td>
                                        <td><input class="input mono num" style="min-width:88px" type="number" step="0.01" min="0.01" wire:model.live="items.{{ $index }}.quantity" aria-label="{{ __('Quantity') }} {{ $index+1 }}" required /></td>
                                        <td><input id="quote-price-{{ $index }}" class="input mono num" style="min-width:110px" type="number" min="0" step="0.01" wire:model.live="items.{{ $index }}.unit_price" required /></td>
                                        <td><input class="input mono num" style="min-width:78px" type="number" step="0.01" min="0" max="100" wire:model.live="items.{{ $index }}.tax_rate" aria-label="{{ __('Tax percent') }} {{ $index+1 }}" /></td>
                                        <td class="mono num">
                                            {{ number_format((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0) * (1 + ((float) ($item['tax_rate'] ?? 0) / 100)),2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="help" style="padding:10px 16px;border-top:1px solid var(--line)">{{ __('All item prices are mandatory in the current workflow. Tax is calculated separately for each line.') }}</p>
                    </section>
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Delivery & terms') }}</h2></div>
                        <div class="card-b fgrid">
                            <div class="field">
                                <label for="q-valid-until">{{ __('Quotation valid until') }}</label>
                                <input id="q-valid-until" class="input" type="date" wire:model="valid_until" required />
                                @error('valid_until')<p class="err">{{ $message }}</p>@enderror
                            </div>
                            <div class="field">
                                <label for="q-notes">{{ __('Notes to the buyer') }}</label>
                                <textarea id="q-notes" class="textarea" rows="3" wire:model="notes"></textarea>
                                @error('notes')<p class="err">{{ $message }}</p>@enderror
                            </div>
                            <div class="field">
                                <label for="q-terms">{{ __('Terms & conditions') }}</label>
                                <textarea id="q-terms" class="textarea" rows="3" wire:model="terms_conditions"></textarea>
                                @error('terms_conditions')<p class="err">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>
                    <div class="row" style="justify-content:flex-end">
                        <button type="button" class="btn btn-p" x-on:click="step='review'">{{ __('Review quotation') }}</button>
                    </div>
                </div>
                <div x-show="step==='review'" x-cloak class="stack">
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Itemised prices') }}</h2><span class="sp"></span><button type="button" class="link sm" x-on:click="step='prices'">{{ __('Edit') }}</button></div>
                        <div class="tbl-wrap"><table class="tbl compact">
                            <thead><tr><th>{{ __('Item') }}</th><th class="num">{{ __('Quantity') }}</th><th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Total incl. tax') }}</th></tr></thead>
                            <tbody>
                            @foreach($items as $item)
                                <tr><td>{{ $item['description'] }}</td><td class="num mono">{{ $item['quantity'] }}</td>
                                    <td class="num mono">{{ isset($item['unit_price']) ? number_format((float) $item['unit_price'],2) : '—' }}</td>
                                    <td class="num mono">{{ number_format((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0) * (1 + ((float) ($item['tax_rate'] ?? 0) / 100)),2) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    </section>
                    <section class="card">
                        <div class="card-h"><h2 class="card-t">{{ __('Quotation summary') }}</h2></div>
                        <div class="card-b"><dl class="kv"><dt>{{ __('Valid until') }}</dt><dd>{{ $valid_until ?: '—' }}</dd><dt>{{ __('Total incl. taxes') }}</dt><dd class="mono b">{{ number_format($this->calculateTotal(),2) }}</dd><dt>{{ __('Notes') }}</dt><dd>{{ $notes ?: '—' }}</dd><dt>{{ __('Terms') }}</dt><dd>{{ $terms_conditions ?: '—' }}</dd></dl></div>
                    </section>
                    <div class="row wrap" style="justify-content:space-between">
                        <button type="button" class="btn" x-on:click="step='prices'">{{ __('Back to edit') }}</button>
                        <button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Submit quotation') }}</button>
                    </div>
                </div>
            </div>
            <aside class="stack">
                <section class="card">
                    <div class="card-h"><h2 class="card-t">{{ __('Quotation totals') }}</h2></div>
                    <div class="card-b stack">
                        <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Items') }}</span><b>{{ count($items) }}</b></div>
                        <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Total incl. taxes') }}</span><b class="mono" style="font-size:20px">{{ number_format($this->calculateTotal(),2) }}</b></div>
                    </div>
                </section>
                <section class="card">
                    <div class="card-h"><h2 class="card-t">{{ __('Request summary') }}</h2></div>
                    <div class="card-b"><dl class="kv">
                        <dt>{{ __('Reference') }}</dt><dd class="mono">RFQ #{{ $invitation->request_id }}</dd>
                        <dt>{{ __('Buyer') }}</dt><dd>{{ $invitation->request?->buyer?->name ?? '—' }}</dd>
                        <dt>{{ __('Deadline') }}</dt><dd>{{ $invitation->request?->deadline?->format('d M Y') ?? '—' }}</dd>
                        <dt>{{ __('Request') }}</dt><dd>{{ $invitation->request?->title }}</dd>
                    </dl></div>
                </section>
                @if($errors->any())
                    <div class="note bad" role="alert"><strong>{{ __('Please correct validation errors before submitting.') }}</strong></div>
                @endif
            </aside>
        </div>
    </form>
</div>
