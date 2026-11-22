<section class="card">
    <div class="card-h"><h2 class="card-t">{{ __('Award decision') }}</h2>
        <span class="card-s">{{ __('Audited procurement workflow') }}</span></div>
    <div class="card-b stack">
        @if($existing)
            <div class="note">{{ __('This RFQ has already been awarded.') }}</div>
            <a class="btn btn-p" href="{{ route('procurement.orders.show', $existing) }}">{{ __('View procurement purchase order') }} #{{ $existing->id }}</a>
        @elseif($request->status !== 'closed')
            <div class="note warn">{{ __('Close the RFQ to enable the award decision. The selected quotation must cover every line.') }}</div>
        @elseif($quotes->isEmpty())
            <div class="note">{{ __('There are no submitted quotations available for award.') }}</div>
        @else
            <form wire:submit="award" class="stack">
                <div class="field">
                    <label for="award-quote">{{ __('Select submitted quotation') }}</label>
                    <select id="award-quote" class="select" wire:model="selectedQuoteId" required>
                        <option value="">{{ __('Choose supplier quotation') }}</option>
                        @foreach($quotes as $quote)
                            <option value="{{ $quote->id }}">#{{ $quote->id }} · {{ $quote->supplier?->company_name ?: $quote->supplier?->name ?: __('Supplier') }} · {{ strtoupper($quote->currency ?? '') }} {{ number_format((float)($quote->total_amount ?? $quote->total_price), 2) }}</option>
                        @endforeach
                    </select>
                    @error('selectedQuoteId')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="award-justification">{{ __('Award justification') }}</label>
                    <textarea id="award-justification" class="textarea" rows="3" maxlength="3000" wire:model="justification" placeholder="{{ __('Required when awarding to a non-cheapest supplier. Explain quality, schedule, or commercial reasons.') }}"></textarea>
                    @error('justification')<span class="err">{{ $message }}</span>@enderror
                </div>
                @error('award')<div class="note bad" role="alert">{{ $message }}</div>@enderror
                <div class="note">{{ __('The service verifies completeness, currency and lowest eligible total, records the decision and creates a draft procurement PO in one transaction.') }}</div>
                <div class="row" style="justify-content:flex-end">
                    <button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="award">{{ __('Award & create draft PO') }}</button>
                </div>
            </form>
        @endif
    </div>
</section>