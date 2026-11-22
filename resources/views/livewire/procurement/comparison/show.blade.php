<div class="stack" x-data="{ visible: {} }">
    @php
        $matrix = $this->matrix;
        $backRoute = auth()->user()?->isAdmin() ? 'rfq.show' : 'buyer.rfq.show';
    @endphp
    <header class="ph">
        <div>
            <div class="row sm muted"><a href="{{ route('procurement.compare.index') }}" wire:navigate class="link">{{ __('Compare quotations') }}</a><span>/</span><span>RFQ #{{ $request->id }}</span></div>
            <h1 class="ph-t">{{ $request->title }}</h1>
            <p class="ph-d">{{ __('Line-by-line supplier comparison using actual quoted prices and tax rates.') }}</p>
        </div>
        <div class="ph-a">
            <a class="btn" href="{{ route($backRoute,$request) }}" wire:navigate>{{ __('Open RFQ') }}</a>
            @if(\Illuminate\Support\Facades\Route::has('export.quote-comparison.pdf'))
                <a class="btn" href="{{ route('export.quote-comparison.pdf',$request) }}"><x-source.icon name="download" :size="15"/>{{ __('Export PDF') }}</a>
            @endif
        </div>
    </header>

    @if(! $matrix['currency_comparable'] && count($matrix['currencies']) > 1)
        <div class="note bad" role="alert">
            {{ __('Quotations use different currencies. Prices are displayed in their original currencies; no cross-currency ranking or award is permitted until they are normalized.') }}
        </div>
    @endif
    @if($request->status !== 'closed' && $request->status !== 'awarded')
        <div class="note">{{ __('Quoting is still open or not yet closed. Awards require a closed RFQ.') }}</div>
    @endif

    <div class="grid g-3">
        <section class="card">
            <div class="card-b stack">
                <span class="sm muted">{{ __('Best overall value') }}</span>
                <span class="h2">{{ __('Not yet assessed') }}</span>
                <p class="sm muted">{{ __('Delivery, assurance and performance data are not available for a reliable weighted recommendation.') }}</p>
            </div>
        </section>
        <section class="card">
            <div class="card-b stack">
                <span class="sm muted">{{ __('Lowest complete price') }}</span>
                @if($matrix['lowest_complete'])
                    <span class="h2">{{ $matrix['lowest_complete']['supplier'] }}</span>
                    <strong>{{ $matrix['lowest_complete']['currency'] }} {{ number_format($matrix['lowest_complete']['total'],2) }}</strong>
                @else
                    <span class="h2">{{ __('Not comparable') }}</span>
                @endif
            </div>
        </section>
        <section class="card"><div class="card-b stack">
            <span class="sm muted">{{ __('Fastest delivery') }}</span>
            <span class="h2">{{ __('Not provided') }}</span>
            <p class="sm muted">{{ __('Delivery lead times must be captured before ranking suppliers by speed.') }}</p>
        </div></section>
    </div>

    <section class="card source-comparison-card">
        <div class="card-h">
            <h2 class="card-t">{{ __('Supplier comparison') }}</h2>
            <span class="card-s">{{ count($matrix['quotes']) }} {{ __('quotations') }} · {{ count($matrix['items']) }} {{ __('requested lines') }}</span>
        </div>
        <div class="card-b">
            <div class="row wrap">
                @foreach($matrix['quotes'] as $quote)
                    <button class="chip" type="button" wire:key="visible-{{ $quote['id'] }}"
                        x-on:click="visible[{{ $quote['id'] }}] = (visible[{{ $quote['id'] }}] === false)"
                        :aria-pressed="(visible[{{ $quote['id'] }}] !== false).toString()">
                        <x-source.icon name="check" :size="12" />{{ $quote['supplier'] }}
                    </button>
                @endforeach
            </div>
        </div>
        <div class="tbl-wrap source-matrix-scroll">
            <table class="tbl source-comparison-matrix">
                <thead>
                    <tr><th scope="col">{{ __('Requested item') }}</th>
                        @foreach($matrix['quotes'] as $quote)
                            <th scope="col" x-show="visible[{{ $quote['id'] }}] !== false">
                                <div class="stack" style="gap:6px">
                                    <strong>{{ $quote['supplier'] }}</strong>
                                    <span class="sm muted">{{ $quote['currency'] }} · {{ $quote['coverage'] }}/{{ count($matrix['items']) }} {{ __('lines') }}</span>
                                    @if(! $quote['complete'])<span class="badge b-warn">{{ __('Partial') }}</span>@endif
                                    @if($quote['status'] === 'accepted')<span class="badge b-ok">{{ __('Awarded') }}</span>@endif
                                    @if($request->status === 'closed' && $quote['status'] === 'submitted' && $quote['complete'] && $matrix['currency_comparable'])
                                        <button type="button" class="btn btn-sm" wire:click="selectForAward({{ $quote['id'] }})">
                                            {{ __('Select for award') }}
                                        </button>
                                    @elseif($request->status === 'closed' && ! $quote['complete'])
                                        <span class="sm muted">{{ __('Partial quotes cannot be awarded as a full RFQ') }}</span>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($matrix['items'] as $line)
                        <tr>
                            <td><strong>{{ $line['name'] }}</strong><div class="t2">{{ $line['quantity'] }} {{ __('requested') }}</div></td>
                            @foreach($matrix['quotes'] as $quote)
                                @php
                                    $entry = $quote['lines'][$line['id']] ?? null;
                                    $minimum = $line['lowest'][$quote['currency']] ?? null;
                                @endphp
                                <td x-show="visible[{{ $quote['id'] }}] !== false">
                                    @if($entry)
                                        <strong>{{ $quote['currency'] }} {{ number_format($entry['price'],2) }}</strong>
                                        <div class="t2">{{ __('Line total') }} {{ number_format($entry['total'],2) }} {{ __('incl. tax') }}</div>
                                        @if($minimum !== null && $minimum > 0)
                                            @if(abs($entry['price'] - $minimum) < 0.005)
                                                <span class="badge b-ok">{{ __('Lowest') }}</span>
                                            @else
                                                <span class="sm muted">+{{ number_format(($entry['price']/$minimum - 1)*100,1) }}%</span>
                                            @endif
                                        @endif
                                    @else
                                        <em class="muted">{{ __('Not quoted') }}</em>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($matrix['quotes']) + 1 }}">{{ __('No requested items to compare.') }}</td></tr>
                    @endforelse
                    <tr>
                        <th scope="row">{{ __('Total including tax') }}</th>
                        @foreach($matrix['quotes'] as $quote)
                            <td x-show="visible[{{ $quote['id'] }}] !== false" class="mono">
                                <strong>{{ $quote['currency'] }} {{ number_format($quote['total'],2) }}</strong>
                                @unless($quote['complete'])<div class="t2">{{ __('Partial quotation — not a comparable full total') }}</div>@endunless
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    @if($confirmingAward)
        <div class="scrim source-dialog" wire:key="comparison-award-dialog" wire:keydown.escape="$set('confirmingAward',false)" wire:click.self="$set('confirmingAward',false)">
            <section class="modal wide" role="dialog" aria-modal="true" aria-labelledby="comparison-award-title">
                <div class="ov-h"><h2 id="comparison-award-title">{{ __('Confirm supplier award') }}</h2><span class="sp"></span>
                    <button class="icon-btn" wire:click="$set('confirmingAward',false)" type="button" aria-label="{{ __('Close') }}"><x-source.icon name="x"/></button></div>
                <form wire:submit="award">
                    <div class="ov-b stack">
                        <div class="note">{{ __('The award creates an audited draft procurement purchase order. A non-cheapest choice requires a written justification of at least 15 characters.') }}</div>
                        @error('award')<div class="note bad" role="alert">{{ $message }}</div>@enderror
                        <div class="field"><label for="award-reason">{{ __('Award justification') }}</label>
                            <textarea class="input" id="award-reason" rows="4" wire:model="justification" placeholder="{{ __('Explain your supplier selection when needed') }}"></textarea>
                            @error('justification')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="ov-f">
                        <button type="button" class="btn" wire:click="$set('confirmingAward',false)">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="award">{{ __('Confirm award and create draft order') }}</button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>