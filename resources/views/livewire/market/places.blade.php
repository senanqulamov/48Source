@php use App\Support\Chart; use App\Support\Money; @endphp
<div>
    <x-ph title="Markets" :desc="$manage ? 'Marketplaces where sellers list products, with the stores and workers attached to each.' : 'Markets open to you. Open one to see what is sold there.'" :crumbs="[['Markets']]">
        @if ($manage)<a class="btn" href="{{ route('markets.index') }}" wire:navigate><x-icon name="sliders" />Manage markets and stores</a>@endif
    </x-ph>
    <x-strip :items="$strip" />
    @if ($markets->isEmpty())
        <x-card><x-empty icon="store" title="No markets yet" :text="$manage ? 'Add a market first, then its stores.' : 'No market is open to you at the moment.'" /></x-card>
    @else
        <div class="m-grid">
            @foreach ($markets as $i => $m)
                @php $list = $products[$m->id] ?? collect(); $url = route('market.products', ['markets' => [$m->id]]); @endphp
                <article class="m-card" style="animation-delay:{{ min($i, 10) * 40 }}ms" wire:key="mk-{{ $m->id }}">
                    <a class="m-top" href="{{ $url }}" wire:navigate aria-label="Open {{ $m->name }}">{!! Chart::marketArt($m->id) !!}<span class="m-st"><x-badge :status="$m->status === 'active' ? 'ok' : 'warn'" :label="$m->status === 'active' ? 'Active' : 'Paused'" /></span><div class="m-title"><h2>{{ $m->name }}</h2><span><x-icon name="pin" :size="13" />{{ $m->city ?: 'No city' }}</span></div></a>
                    <div class="m-body">
                        <div class="m-facts"><div><b class="mono">{{ $list->count() }}</b><span>Products</span></div><div><b class="mono">{{ $open[$m->id] ?? 0 }}</b><span>Open orders</span></div><div><b class="mono">{{ $workers[$m->id] ?? 0 }}</b><span>Workers</span></div></div>
                        @if ($list->isNotEmpty())<div class="m-thumbs">@foreach ($list->take(4) as $o)<span class="s-th" title="{{ $o->product->name }} · {{ Money::format($o->price_cents, $o->currency) }}">{!! Chart::productArt($o->product->sku, $o->product->category?->name ?? 'General') !!}</span>@endforeach<span class="muted sm">Sold here</span></div>@endif
                        <div class="row"><span class="muted sm">{{ ($byMarket[$m->id] ?? collect())->count() }} store{{ ($byMarket[$m->id] ?? collect())->count() === 1 ? '' : 's' }}@if ($mine === $m->id) · your store is here @endif</span><span class="sp"></span><a class="btn btn-sm btn-p" href="{{ $url }}" wire:navigate>Open market</a></div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
