@php use App\Support\Money; @endphp
<div>
    <x-ph title="Source a request" desc="For each item of a request, see which stores sell it and at what price. Buy every item from the seller you choose." :crumbs="[['Marketplace', route('market.shop')], ['Source a request']]">
        <a class="btn {{ $basket ? 'btn-p' : '' }}" href="{{ route('market.basket') }}" wire:navigate><x-icon name="box" />Basket @if ($basket)<span class="sb-count" style="margin-left:4px">{{ $basket }}</span>@endif</a>
    </x-ph>
    <section class="card" style="margin-bottom:16px"><div class="toolbar" style="border-bottom:0"><label class="lbl" for="src-r">Request</label>
        <div style="flex:1 1 320px;max-width:560px"><select id="src-r" class="select" wire:model.live="number"><option value="">Choose a request</option>@foreach ($list as $r)<option value="{{ $r->number }}">{{ $r->number }} - {{ $r->title }}</option>@endforeach</select></div>
        @if ($rfq && $covered)<span class="sp"></span><span class="muted sm">Cheapest mix: <b class="mono" style="color:var(--text)">{{ Money::format($mix, 'USD') }}</b> from {{ $stores }} store{{ $stores === 1 ? '' : 's' }}, {{ $covered }} of {{ $plan->count() }} items</span><button type="button" class="btn btn-p" wire:click="cheapest" wire:loading.attr="disabled"><x-icon name="check" />Add cheapest mix</button>@endif
    </div></section>
    @if (! $rfq)
        <x-card><x-empty icon="cols" title="Choose a request" :text="$list->isEmpty() ? 'You have no open or won requests to source for.' : 'Pick a request above to see matching products for each of its items.'" /></x-card>
    @else
        <div class="stack">
            @foreach ($plan as $row)
                @php $it = $row['item']; $got = $cart[$it->id] ?? collect(); @endphp
                <x-card :flush="true" wire:key="si-{{ $it->id }}">
                    <div class="card-h"><div class="grow"><h2 class="card-t">{{ $it->name }}</h2><span class="card-s">Needed: <b class="mono">{{ $it->qty() }} {{ $it->unit }}</b> · {{ $row['offers']->count() }} matching offer{{ $row['offers']->count() === 1 ? '' : 's' }}</span></div>
                        @if ($got->isNotEmpty())<x-badge status="ok" :label="'In basket: ' . $got->map(fn ($c) => Money::qty($c->quantity_milli) . ' from ' . $c->offer->store->name)->join(', ')" />@endif</div>
                    @if ($row['offers']->isEmpty())
                        <div class="card-b muted">No store sells a matching product in {{ $it->unit }}. <a class="link" href="{{ route('market.shop', ['q' => \Illuminate\Support\Str::of($it->name)->explode(' ')->first()]) }}" wire:navigate>Search the marketplace</a></div>
                    @else
                        <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Product and store</th><th class="num">Unit price</th><th class="num hide-sm">Smallest order</th><th class="num hide-sm">In stock</th><th class="num hide-md">Ready in</th><th class="num">For {{ $it->qty() }}</th><th style="width:230px"></th></tr></thead><tbody>
                            @foreach ($row['offers']->take(6) as $k => $o)
                                @php $enough = $o->stock_milli >= max($it->quantity_milli, $o->moq_milli); @endphp
                                <tr wire:key="so-{{ $it->id }}-{{ $o->id }}"><td class="tmain"><div class="t1">{{ $o->product->name }} @if ($row['best']?->id === $o->id)<span class="badge b-ok plain">Best match with stock</span>@endif @unless ($o->exact)<span class="badge b-warn plain">Check the size</span>@endunless</div><div class="t2">{{ $o->store->name }} · {{ $o->store->market->name }}</div></td>
                                    <td class="num mono b">{{ Money::format($o->price_cents, $o->currency) }}</td><td class="num mono hide-sm">{{ Money::qty($o->moq_milli) }}</td>
                                    <td class="num mono hide-sm" @unless ($enough) style="color:var(--bad)" @endunless>{{ Money::qty($o->stock_milli) }}</td><td class="num mono hide-md">{{ $o->lead_days }} d</td>
                                    <td class="num mono">{{ Money::format(Money::lineTotal($o->price_cents, $it->quantity_milli), $o->currency, false) }}</td>
                                    <td><div class="row" style="justify-content:flex-end"><input class="input mono" style="width:96px;height:32px" inputmode="decimal" wire:model="qty.{{ $it->id }}.{{ $o->id }}" placeholder="{{ Money::qtyInput(min($o->stock_milli, max($it->quantity_milli, $o->moq_milli))) }}" aria-label="Quantity from {{ $o->store->name }}"><button type="button" class="btn btn-sm" wire:click="add({{ $it->id }}, {{ $o->id }})">Add</button></div></td></tr>
                            @endforeach
                        </tbody></table></div>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
</div>
