@php use App\Support\Money; use App\Support\Chart; @endphp
<div>
    <x-ph title="Marketplace" desc="Products from the stores in every market. Compare the sellers of each product, then put what you need in the basket." :crumbs="[['Marketplace']]">
        @if ($canBuy)<a class="btn" href="{{ route('market.sourcing') }}" wire:navigate><x-icon name="cols" />Source a request</a><a class="btn btn-p" href="{{ route('market.basket') }}" wire:navigate><x-icon name="box" />Basket @if ($basket)<span class="sb-count" style="margin-left:4px">{{ $basket }}</span>@endif</a>@endif
    </x-ph>
    <section class="card" style="margin-bottom:16px"><div class="toolbar" style="border-bottom:0">
        <div class="dt-search block" style="flex:1 1 280px"><x-icon name="search" /><input type="search" wire:model.live.debounce.300ms="q" placeholder="Search product, SKU or store" aria-label="Search products"><kbd>/</kbd></div>
        <select class="select" style="width:200px" wire:model.live="cat" aria-label="Category"><option value="">All categories</option>@foreach ($cats as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
        <select class="select" style="width:190px" wire:model.live="market" aria-label="Market"><option value="">All markets</option>@foreach ($markets as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
        <select class="select" style="width:190px" wire:model.live="sort" aria-label="Sort"><option value="name">Name A to Z</option><option value="low">Lowest price first</option><option value="high">Highest price first</option><option value="stock">Most stock first</option></select>
    </div></section>
    @if ($rows->isEmpty())
        <x-card><x-empty icon="search" title="No products match" text="Try another word or clear the filters."><button type="button" class="btn" wire:click="clear">Clear filters</button></x-empty></x-card>
    @else
        <div class="p-grid">
            @foreach ($rows as $i => $p)
                @php $n = $p->offers->count(); $stock = (int) $p->stock; $mk = $p->offers->pluck('store.market_id')->unique()->count(); @endphp
                <div class="p-card" style="animation-delay:{{ min($i, 12) * 25 }}ms" wire:key="p-{{ $p->id }}"><button type="button" class="p-link" style="all:unset;display:block;cursor:pointer;width:100%" wire:click="show({{ $p->id }})" aria-label="{{ $p->name }}, {{ $n }} seller{{ $n === 1 ? '' : 's' }}">
                    <div class="p-pic">{!! Chart::productArt($p->sku, $p->category?->name ?? 'General') !!}<span class="p-cat">{{ $p->category?->name ?? 'General' }}</span></div>
                    <div class="p-body"><div class="p-name">{{ $p->name }}</div><div class="muted sm"><span class="mono">{{ $p->sku }}</span> · {{ $n }} seller{{ $n === 1 ? '' : 's' }}</div>
                        <div class="p-foot"><div>@if ($n > 1)<span class="muted sm">from </span>@endif<span class="p-price">{{ Money::format((int) $p->min_price, $p->offers->first()->currency) }}</span><span class="muted sm"> / {{ $p->unit }}</span></div><span class="p-stock {{ $stock > 10000 ? '' : 'low' }}">{{ Money::qty($stock) }} in stock</span></div>
                        <div class="p-more"><span>{{ $mk }} market{{ $mk === 1 ? '' : 's' }}</span><span class="p-go">Compare sellers<x-icon name="right" :size="13" /></span></div></div>
                </button></div>
            @endforeach
        </div>
        <div class="card" style="margin-top:16px">{{ $rows->links('livewire.simple-pager') }}</div>
    @endif
    @if ($current)
        <x-drawer :title="$current->name">
            <div class="p-hero" style="margin-bottom:16px">{!! Chart::productArt($current->sku, $current->category?->name ?? 'General', true) !!}<div class="p-hero-t"><span class="tag">{{ $current->category?->name ?? 'General' }}</span><div class="muted sm mono">{{ $current->sku }}</div><div class="muted sm">Sold per {{ $current->unit }}</div></div></div>
            @if ($current->description)<p style="margin-bottom:14px">{{ $current->description }}</p>@endif
            <h3 class="lbl" style="margin-bottom:8px">{{ $current->offers->count() }} seller{{ $current->offers->count() === 1 ? '' : 's' }}, cheapest first</h3>
            <div class="stack" style="gap:10px">
                @forelse ($current->offers as $k => $o)
                    <div class="card" wire:key="o-{{ $o->id }}" style="padding:12px 14px">
                        <div class="row" style="align-items:flex-start"><div class="grow"><div class="t1">{{ $o->store->name }} @if ($k === 0 && $current->offers->count() > 1)<span class="badge b-ok plain">Lowest price</span>@endif</div><div class="muted sm">{{ $o->store->market->name }}{{ $o->store->stall ? ' · stall ' . $o->store->stall : '' }}</div></div>
                            <div style="text-align:right"><div class="p-price" style="font-size:18px">{{ Money::format($o->price_cents, $o->currency) }}</div><div class="muted xs">per {{ $current->unit }}</div></div></div>
                        <div class="row wrap sm muted" style="gap:14px;margin-top:8px"><span>Smallest order <b style="color:var(--text)">{{ Money::qty($o->moq_milli) }}</b></span><span>In stock <b style="color:var(--text)">{{ Money::qty($o->stock_milli) }}</b></span><span>Ready in <b style="color:var(--text)">{{ $o->lead_days }} day{{ $o->lead_days === 1 ? '' : 's' }}</b></span></div>
                        @if ($canBuy)
                            <div class="row" style="margin-top:10px"><input class="input mono" style="width:110px" inputmode="decimal" wire:model="qty.{{ $o->id }}" placeholder="{{ Money::qtyInput($o->moq_milli) }}" aria-label="Quantity from {{ $o->store->name }}"><span class="muted sm">{{ $current->unit }}</span><span class="sp"></span>
                                @if (isset($inBasket[$o->id]))<span class="muted sm">{{ Money::qty($inBasket[$o->id]) }} in basket</span>@endif
                                <button type="button" class="btn btn-sm btn-p" wire:click="add({{ $o->id }})" wire:loading.attr="disabled"><x-icon name="plus" :size="14" />{{ isset($inBasket[$o->id]) ? 'Update' : 'Add to basket' }}</button></div>
                        @endif
                    </div>
                @empty
                    <p class="faint">No store has this product in stock right now.</p>
                @endforelse
            </div>
            @if ($canBuy)<x-slot:foot><span class="sp"></span><a class="btn btn-p" href="{{ route('market.basket') }}" wire:navigate><x-icon name="box" />Open basket</a></x-slot:foot> @endif
        </x-drawer>
    @endif
</div>
