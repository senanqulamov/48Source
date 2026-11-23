@php use App\Support\Money; @endphp
<div>
    <x-ph title="Basket" :desc="$lines ? 'One order is placed for each store. Stock is reserved when you place the orders.' : 'Nothing here yet.'" :crumbs="[['Marketplace', route('market.shop')], ['Basket']]">
        <a class="btn" href="{{ route('market.shop') }}" wire:navigate><x-icon name="plus" />Add more products</a>
    </x-ph>
    @if ($groups->isEmpty())
        <x-card><x-empty icon="box" title="Your basket is empty" text="Browse the marketplace, or let the sourcing page find sellers for a request."><a class="btn btn-p" href="{{ route('market.shop') }}" wire:navigate>Open marketplace</a><a class="btn" href="{{ route('market.sourcing') }}" wire:navigate>Source a request</a></x-empty></x-card>
    @else
        <div class="grid g-2-1">
            <div class="stack">
                @foreach ($groups as $g)
                    <x-card :title="$g['store']->name" :sub="$g['store']->market->name . ' · becomes one order'" :flush="true" wire:key="g-{{ $g['store']->id }}">
                        <x-slot:act><b class="mono">{{ Money::format($g['total'], $g['lines']->first()->offer->currency) }}</b></x-slot:act>
                        <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Product</th><th class="num">Unit price</th><th style="width:130px">Quantity</th><th class="num">Total</th><th style="width:44px"></th></tr></thead><tbody>
                            @foreach ($g['lines'] as $c)
                                <tr wire:key="c-{{ $c->id }}"><td class="tmain"><div class="t1">{{ $c->offer->product->name }}</div><div class="t2"><span class="mono">{{ $c->offer->product->sku }}</span>@if ($c->rfqItem) · for <span class="mono">{{ $c->rfqItem->rfq->number }}</span>@endif · smallest order {{ Money::qty($c->offer->moq_milli) }}</div></td>
                                    <td class="num mono">{{ Money::format($c->offer->price_cents, $c->offer->currency) }}</td>
                                    <td><input class="input mono" inputmode="decimal" wire:model="qty.{{ $c->id }}" wire:change="setQty({{ $c->id }})" aria-label="Quantity of {{ $c->offer->product->name }}"></td>
                                    <td class="num mono b">{{ Money::format($c->line_cents, $c->offer->currency) }}</td>
                                    <td><button type="button" class="icon-btn" wire:click="remove({{ $c->id }})" aria-label="Remove {{ $c->offer->product->name }}"><x-icon name="trash" :size="15" /></button></td></tr>
                            @endforeach
                        </tbody></table></div>
                    </x-card>
                @endforeach
            </div>
            <x-card title="Place the orders">
                <dl class="kv" style="grid-template-columns:1fr auto;margin-bottom:14px"><dt>Products</dt><dd class="mono">{{ $lines }}</dd><dt>Stores</dt><dd class="mono">{{ $groups->count() }}</dd><dt>Orders created</dt><dd class="mono">{{ $groups->count() }}</dd><dt class="b">Total</dt><dd class="mono b" style="font-size:16px">{{ Money::format($total, 'USD') }}</dd></dl>
                <div class="stack" style="gap:14px"><x-field label="Deliver to" for="bk-to"><input id="bk-to" class="input" wire:model="deliverTo" placeholder="Site, depot or address"></x-field><x-field label="Note to the stores" for="bk-n" :opt="true"><textarea id="bk-n" class="textarea" rows="2" wire:model="note"></textarea></x-field></div>
                <button type="button" class="btn btn-p" style="width:100%;justify-content:center;margin-top:16px;height:44px" wire:click="checkout" wire:loading.attr="disabled"><x-icon name="check" />Place {{ $groups->count() === 1 ? 'the order' : $groups->count() . ' orders' }}</button>
                <p class="help" style="margin-top:8px">Each store confirms its own order. You can cancel an order until its store confirms it.</p>
            </x-card>
        </div>
    @endif
</div>
