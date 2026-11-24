@php
    use App\Support\Money; use App\Support\Chart;
    $products = $page === 'products';
    $cur = fn ($offers) => $offers->first()?->currency ?? 'USD';
    $avail = function (int $stock) { return $stock <= 0 ? ['bad', 'Unavailable'] : ($stock <= 10000 ? ['warn', 'Low'] : null); };
@endphp
<div>
    <x-ph :title="$products ? 'Products' : 'Marketplace'" :desc="$products ? 'Products you can see across the markets open to you. Compare the offers of each one, add what you need to the basket and place an order.' : 'Search every seller and market at once. Open a product to compare its offers side by side, then take each item from the seller that suits you. One basket, as many sellers as you like.'" :crumbs="[[$products ? 'Products' : 'Marketplace']]">
        @if ($canBuy)
            @unless ($products)<a class="btn" href="{{ route('market.sourcing') }}" wire:navigate><x-icon name="inbox" />Source a request</a>@endunless
            <a class="btn" href="{{ route('market.basket') }}" wire:navigate><x-icon name="box" />Basket @if ($basket)<span class="sb-count" style="margin-left:4px">{{ $basket }}</span>@endif</a>
        @endif
    </x-ph>

    @if ($products)
        <x-strip :items="$strip" />
        <div class="p-bar">
            <div class="tb-search p-search" style="max-width:none;flex:1 1 260px"><x-icon name="search" /><input type="search" wire:model.live.debounce.300ms="q" placeholder="Search product, SKU or seller" aria-label="Search products" autocomplete="off"></div>
            <div style="width:190px;flex:none"><select class="select" wire:model.live="sort" aria-label="Sort"><option value="rel">Name A-Z</option><option value="low">Price: low to high</option><option value="high">Price: high to low</option><option value="lead">Fastest delivery</option></select></div>
            <div style="width:190px;flex:none"><select class="select" wire:model.live="avail" aria-label="Availability"><option value="">Any availability</option><option value="in">In stock</option><option value="low">Low stock</option><option value="out">Unavailable</option></select></div>
            <div style="width:190px;flex:none"><select class="select" wire:model.live="markets.0" aria-label="Market"><option value="">All markets</option>@foreach ($marketList as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
            <div class="seg" role="group" aria-label="View"><button type="button" class="{{ $view === 'showcase' ? 'on' : '' }}" aria-pressed="{{ $view === 'showcase' ? 'true' : 'false' }}" wire:click="setView('showcase')">Showcase</button><button type="button" class="{{ $view === 'table' ? 'on' : '' }}" aria-pressed="{{ $view === 'table' ? 'true' : 'false' }}" wire:click="setView('table')">Table</button></div>
        </div>
        @php $top = $catNames->take(7); if ($cat !== '' && ! $top->has((int) $cat) && $catNames->has((int) $cat)) { $top = $top->take(6)->put((int) $cat, $catNames[(int) $cat]); } $rest = $catNames->count() - $top->count(); @endphp
        <div class="p-chips one">
            <button type="button" class="p-chip {{ $cat === '' ? 'on' : '' }}" wire:click="setCat('')">All <span class="n">{{ $total }}</span></button>
            @foreach ($top as $id => $name)<button type="button" class="p-chip {{ $cat === (string) $id ? 'on' : '' }}" wire:click="setCat('{{ $id }}')" wire:key="chip-{{ $id }}">{{ $name }} <span class="n">{{ $counts[$id] ?? 0 }}</span></button>@endforeach
            @if ($rest > 0)
                <span x-data="pop({ w: 340, left: true })" style="display:contents">
                    <button type="button" class="p-chip more" x-on:click="toggle($el)" aria-haspopup="true"><span>All categories</span> <span class="n">{{ $catNames->count() }}</span><x-icon name="down" :size="14" /></button>
                    <div class="pop c-pop" x-ref="p" x-show="open" x-cloak wire:ignore.self :style="pos"><div x-data="flist" x-effect="if (open) $nextTick(() => $refs.find && $refs.find.focus())">
                        <div class="f-find"><x-icon name="search" :size="15" /><input type="search" x-ref="find" x-model="t" placeholder="Find a category" aria-label="Find a category" autocomplete="off"></div>
                        <button type="button" class="pop-i {{ $cat === '' ? 'on' : '' }}" x-show="!t" wire:click="setCat('')" x-on:click="close()"><span style="flex:1;min-width:0">All categories</span><span class="k">{{ $total }}</span></button>
                        @foreach ($catNames->sortBy(fn ($n) => mb_strtolower($n)) as $id => $name)<button type="button" class="pop-i {{ $cat === (string) $id ? 'on' : '' }}" data-n="{{ $name }}" x-show="hit($el, true)" wire:click="setCat('{{ $id }}')" x-on:click="close()" wire:key="cp-{{ $id }}"><span style="flex:1;min-width:0">{{ $name }}</span><span class="k">{{ $counts[$id] ?? 0 }}</span></button>@endforeach
                        <div class="f-none" x-show="none" x-cloak>Nothing found</div>
                    </div></div>
                </span>
            @endif
        </div>
    @endif

    <div @class(['s-lay' => ! $products])>
        @unless ($products)
            @php $short = 5; $nSt = count($stores); $nMk = count($markets); @endphp
            <aside class="card s-filt" aria-label="Filters">
                <div class="s-fh"><b>Filters</b>@if ($used)<span class="sb-count" style="margin-left:6px">{{ count($used) }}</span>@endif<span class="sp"></span>@if ($used)<button type="button" class="link sm" wire:click="clear">Clear all</button>@endif</div>

                <div class="s-fg" x-data="flist">
                    <button type="button" class="s-fl" x-on:click="shut = !shut" :aria-expanded="!shut"><span>Category</span><span class="sp"></span><x-icon name="down" :size="14" /></button>
                    <div x-show="!shut">
                        @if ($catNames->count() > $short)<div class="f-find"><x-icon name="search" :size="15" /><input type="search" x-model="t" placeholder="Find a category" aria-label="Find a category" autocomplete="off"></div>@endif
                        <div class="f-list" :class="(more || t) && 'all'">
                            <button type="button" class="s-fi {{ $cat === '' ? 'on' : '' }}" style="order:-2" x-show="!t" aria-pressed="{{ $cat === '' ? 'true' : 'false' }}" wire:click="setCat('')"><span>All categories</span><span class="n">{{ $total }}</span></button>
                            @foreach ($catNames->keys() as $i => $id)
                                @php $name = $catNames[$id]; $on = $cat === (string) $id; @endphp
                                <button type="button" class="s-fi {{ $on ? 'on' : '' }}" @if ($on) style="order:-1" @endif data-n="{{ $name }}" x-show="hit($el, {{ $i < $short || $on ? 'true' : 'false' }})" @if ($i >= $short && ! $on) x-cloak @endif aria-pressed="{{ $on ? 'true' : 'false' }}" wire:click="setCat('{{ $id }}')" wire:key="fc-{{ $id }}-{{ $i < $short || $on ? 1 : 0 }}" title="{{ $name }}"><span>{{ $name }}</span><span class="n">{{ $counts[$id] ?? 0 }}</span></button>
                            @endforeach
                            <div class="f-none" x-show="none" x-cloak>Nothing found</div>
                        </div>
                        @if ($catNames->count() > $short)<button type="button" class="f-more" x-show="!t" x-on:click="more = !more"><span x-show="!more">Show all {{ $catNames->count() }}</span><span x-show="more" x-cloak>Show fewer</span></button>@endif
                    </div>
                </div>

                @foreach ([['Seller', 'stores', $storeList, $storeCounts, $stores, 'Find a seller', 'fs'], ['Market', 'markets', $marketList, collect(), $markets, 'Find a market', 'fm']] as [$gl, $gm, $gopts, $gcounts, $gsel, $gph, $gk])
                    @if ($gopts->isNotEmpty())
                        @php $sel = array_map('strval', $gsel); @endphp
                        <div class="s-fg" x-data="flist">
                            <button type="button" class="s-fl" x-on:click="shut = !shut" :aria-expanded="!shut"><span>{{ $gl }}</span>@if ($sel)<span class="sb-count">{{ count($sel) }}</span>@endif<span class="sp"></span><x-icon name="down" :size="14" /></button>
                            <div x-show="!shut">
                                @if ($gopts->count() > $short)<div class="f-find"><x-icon name="search" :size="15" /><input type="search" x-model="t" placeholder="{{ $gph }}" aria-label="{{ $gph }}" autocomplete="off"></div>@endif
                                <div class="f-list" :class="(more || t) && 'all'">
                                    @foreach ($gopts->keys() as $i => $id)
                                        @php $name = $gopts[$id]; $on = in_array((string) $id, $sel, true); @endphp
                                        <label class="s-ck" @if ($on) style="order:-1" @endif data-n="{{ $name }}" x-show="hit($el, {{ $i < $short || $on ? 'true' : 'false' }})" @if ($i >= $short && ! $on) x-cloak @endif wire:key="{{ $gk }}-{{ $id }}-{{ $i < $short || $on ? 1 : 0 }}" title="{{ $name }}"><input type="checkbox" class="chk" value="{{ $id }}" wire:model.live="{{ $gm }}"><span class="grow">{{ $name }}</span>@if (isset($gcounts[$id]))<span class="n">{{ $gcounts[$id] }}</span>@endif</label>
                                    @endforeach
                                    <div class="f-none" x-show="none" x-cloak>Nothing found</div>
                                </div>
                                @if ($gopts->count() > $short)<button type="button" class="f-more" x-show="!t" x-on:click="more = !more"><span x-show="!more">Show all {{ $gopts->count() }}</span><span x-show="more" x-cloak>Show fewer</span></button>@endif
                            </div>
                        </div>
                    @endif
                @endforeach

                <div class="s-fg"><div class="s-fl">Delivery within</div>
                    <div class="seg" role="group" aria-label="Delivery within">@foreach (['' => 'Any', '2' => '2 d', '5' => '5 d', '7' => '7 d'] as $k => $l)<button type="button" class="{{ $lead === (string) $k ? 'on' : '' }}" aria-pressed="{{ $lead === (string) $k ? 'true' : 'false' }}" wire:click="setLead('{{ $k }}')">{{ $l }}</button>@endforeach</div>
                </div>
            </aside>
        @endunless

        <div style="min-width:0">
            @unless ($products)
                <div class="p-bar" style="margin-top:0">
                    <div class="tb-search p-search s-search" style="max-width:none;flex:1 1 260px"><x-icon name="search" /><input type="search" wire:model.live.debounce.300ms="q" placeholder='Search products, e.g. "rebar 16 mm", "cement", a SKU or a seller' aria-label="Search the marketplace" autocomplete="off"></div>
                    <div style="width:210px;flex:none"><select class="select" wire:model.live="sort" aria-label="Sort"><option value="rel">Best match</option><option value="low">Price: low to high</option><option value="high">Price: high to low</option><option value="lead">Fastest delivery</option></select></div>
                    <div class="seg" role="group" aria-label="View"><button type="button" class="{{ $view === 'product' ? 'on' : '' }}" aria-pressed="{{ $view === 'product' ? 'true' : 'false' }}" wire:click="setView('product')">By product</button><button type="button" class="{{ $view === 'offers' ? 'on' : '' }}" aria-pressed="{{ $view === 'offers' ? 'true' : 'false' }}" wire:click="setView('offers')">All offers</button></div>
                </div>
                @if ($used)
                    <div class="f-used" aria-label="Filters in use">
                        @foreach ($used as [$uk, $uid, $ul, $uv])<span class="f-tag" wire:key="u-{{ $uk }}-{{ $uid }}"><span class="muted">{{ $ul }}:</span> <b>{{ $uv }}</b><button type="button" class="icon-btn" wire:click="drop('{{ $uk }}', '{{ $uid }}')" aria-label="Remove filter {{ $uv }}" title="Remove"><x-icon name="x" :size="13" /></button></span>@endforeach
                        @if (count($used) > 1)<button type="button" class="link sm" wire:click="clear">Clear all</button>@endif
                    </div>
                @endif
            @endunless

            @if ($offerRows !== null)
                <section class="card">
                    @if ($offerRows->isEmpty())<x-empty icon="search" title="No offers match" text="Try another word or clear the filters."><button type="button" class="btn" wire:click="clear">Clear filters</button></x-empty>
                    @else
                        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Product</th><th>Seller</th><th class="hide-md">Market</th><th class="num">Price</th><th class="hide-sm">In stock</th><th class="num hide-sm">Delivery</th><th class="num hide-md">Min. order</th><th></th></tr></thead><tbody>
                            @foreach ($offerRows as $o)
                                <tr wire:key="or-{{ $o->id }}" data-plain>
                                    <td><div class="row"><span class="s-th">{!! Chart::productArt($o->product->sku, $o->product->category?->name ?? 'General') !!}</span><div><div class="t1">{{ $o->product->name }}</div><div class="t2">{{ $o->product->category?->name ?? 'General' }} · <span class="mono">{{ $o->product->sku }}</span></div></div></div></td>
                                    <td>{{ $o->store->name }}</td><td class="hide-md">{{ $o->store->market?->name }}</td>
                                    <td class="num"><b class="mono">{{ Money::format($o->price_cents, $o->currency) }}</b><span class="muted sm"> / {{ $o->product->unit }}</span></td>
                                    <td class="hide-sm"><span class="p-stock {{ $o->stock_milli <= 10000 ? 'low' : '' }}">{{ Money::qty($o->stock_milli) }} {{ $o->product->unit }}</span></td>
                                    <td class="num mono hide-sm">{{ $o->lead_days }} d</td><td class="num mono hide-md">{{ Money::qty($o->moq_milli) }}</td>
                                    <td class="num"><div class="row" style="justify-content:flex-end"><button type="button" class="btn btn-sm" wire:click="show({{ $o->product_id }})">Compare</button>@if ($canBuy)<button type="button" class="btn btn-sm btn-p" wire:click="add({{ $o->id }})" wire:loading.attr="disabled" wire:target="add({{ $o->id }})"><x-icon name="plus" :size="14" />{{ isset($inBasket[$o->id]) ? 'Added' : 'Add' }}</button>@endif</div></td>
                                </tr>
                            @endforeach
                        </tbody></table></div>
                        {{ $offerRows->links('livewire.simple-pager') }}
                    @endif
                </section>
            @elseif ($rows->isEmpty())
                <x-card><x-empty icon="search" title="No products match" text="Try another word or clear the filters."><button type="button" class="btn" wire:click="clear">Clear filters</button></x-empty></x-card>
            @elseif ($products && $view === 'table')
                <section class="card"><div class="tbl-wrap"><table class="tbl"><thead><tr><th class="hide-sm">SKU</th><th>Product</th><th class="hide-md">Category</th><th class="num">Sellers</th><th class="num">From</th><th>Availability</th><th class="num hide-md">Fastest</th><th></th></tr></thead><tbody>
                    @foreach ($rows as $p)
                        @php $a = $avail((int) $p->stock_sum); @endphp
                        <tr wire:key="pt-{{ $p->id }}" class="click" wire:click="show({{ $p->id }})" tabindex="0" wire:keydown.enter="show({{ $p->id }})" data-plain>
                            <td class="mono hide-sm">{{ $p->sku }}</td><td class="t1">{{ $p->name }}</td><td class="hide-md muted">{{ $p->category?->name ?? 'General' }}</td><td class="num mono">{{ $p->sellers }}</td>
                            <td class="num mono">{{ Money::format((int) $p->min_price, $cur($p->offers)) }}<span class="faint"> /{{ $p->unit }}</span></td>
                            <td>@if ($a)<x-badge :status="$a[0]" :label="$a[1]" />@else<x-badge status="ok" label="In stock" />@endif</td>
                            <td class="num mono hide-md">{{ $p->min_lead !== null ? $p->min_lead . ' d' : '-' }}</td>
                            <td class="num"><button type="button" class="btn btn-sm" wire:click.stop="show({{ $p->id }})">Compare {{ $p->sellers }} offer{{ $p->sellers === 1 ? '' : 's' }}</button></td>
                        </tr>
                    @endforeach
                </tbody></table></div>{{ $rows->links('livewire.simple-pager') }}</section>
            @else
                <div class="p-grid">
                    @foreach ($rows as $i => $p)
                        @php
                            $n = (int) $p->sellers; $stock = (int) $p->stock_sum; $c = $cur($p->offers);
                            $max = (int) $p->offers->max('price_cents'); $min = (int) $p->min_price; $a = $avail($stock);
                            $save = $n > 1 && $max > $min ? (int) round(($max - $min) * 100 / $max) : 0;
                        @endphp
                        <div class="p-card" style="animation-delay:{{ min($i, 12) * 25 }}ms" wire:key="p-{{ $p->id }}">
                            <button type="button" class="p-link s-open" wire:click="show({{ $p->id }})" aria-label="Compare offers for {{ $p->name }}">
                                <div class="p-pic">{!! Chart::productArt($p->sku, $p->category?->name ?? 'General') !!}<span class="p-cat">{{ $p->category?->name ?? 'General' }}</span>@if ($a)<span class="badge b-{{ $a[0] }}" style="position:absolute;right:10px;top:10px">{{ $a[1] }}</span>@endif</div>
                                <div class="p-body"><div class="p-name">{{ $p->name }}</div>
                                    @if ($products)<div class="muted sm"><span class="mono">{{ $p->sku }}</span> · {{ $p->offers->first()?->store->name }}</div>@endif
                                    <div class="p-foot"><div>@if ($n > 1)<span class="muted sm">from </span>@endif<span class="p-price">{{ Money::format($min, $c) }}</span><span class="muted sm"> / {{ $p->unit }}</span></div></div>
                                    <div class="s-meta"><span><x-icon name="store" :size="13" /> {{ $n }} seller{{ $n === 1 ? '' : 's' }}</span>@if ($p->min_lead !== null)<span><x-icon name="truck" :size="13" /> from {{ $p->min_lead }} d</span>@endif<span>{{ Money::qty($stock) }} in stock</span></div>
                                    @if ($save)<div class="s-save">Price range {{ Money::format($min, $c) }} - {{ Money::format($max, $c) }} · compare to save up to {{ $save }}%</div>@endif
                                </div>
                            </button>
                            <div class="p-buy"><button type="button" class="btn btn-sm btn-p" style="width:100%" wire:click="show({{ $p->id }})"><x-icon name="cols" :size="14" />Compare {{ $n }} offer{{ $n === 1 ? '' : 's' }}</button></div>
                        </div>
                    @endforeach
                </div>
                <div class="card" style="margin-top:16px">{{ $rows->links('livewire.simple-pager') }}</div>
            @endif
        </div>
    </div>

    @if ($current)
        @php
            $of = $current->offers; $c = $cur($of); $low = $of->first(); $fast = $of->sortBy('lead_days')->first();
            $sum = (int) $of->sum('stock_milli'); $sellers = $of->pluck('store_id')->unique()->count();
        @endphp
        <x-drawer title="Compare offers">
            <div class="p-hero" style="grid-template-columns:150px 1fr;padding:10px;margin-bottom:14px">{!! Chart::productArt($current->sku, $current->category?->name ?? 'General', true) !!}<div class="p-hero-t"><span class="tag">{{ $current->category?->name ?? 'General' }}</span><div class="b" style="font-size:16px;line-height:1.3">{{ $current->name }}</div><div class="muted sm"><span class="mono">{{ $current->sku }}</span> · {{ $of->count() }} offer{{ $of->count() === 1 ? '' : 's' }} from {{ $sellers }} seller{{ $sellers === 1 ? '' : 's' }}</div></div></div>
            @if ($current->description)<p style="margin-bottom:14px">{{ $current->description }}</p>@endif
            @if ($of->isEmpty())
                <x-empty icon="box" title="Not available right now" text="No store has this product in stock at the moment." />
            @else
                <div class="h-sum" style="margin-bottom:14px"><div><b class="mono">{{ Money::format($low->price_cents, $c) }}</b><span>lowest price · {{ $low->store->name }}</span></div><div><b class="mono">{{ $fast->lead_days }} d</b><span>fastest · {{ $fast->store->name }}</span></div><div><b class="mono">{{ Money::qty($sum) }}</b><span>{{ $current->unit }} in stock in total</span></div></div>
                <div class="o-list">
                    @foreach ($of as $k => $o)
                        @php $more = $low->price_cents > 0 ? (int) round(($o->price_cents - $low->price_cents) * 100 / $low->price_cents) : 0; @endphp
                        <div class="o-row {{ $k === 0 ? 'best' : '' }}" wire:key="o-{{ $o->id }}">
                            <div class="o-main"><div class="row" style="flex-wrap:wrap;gap:6px"><b>{{ $o->store->name }}</b>@if ($k === 0 && $of->count() > 1)<span class="badge b-ok plain">Lowest price</span>@endif @if ($o->id === $fast->id && $of->count() > 1 && $o->lead_days < $low->lead_days)<span class="badge b-info plain">Fastest</span>@endif</div>
                                <div class="muted sm"><x-icon name="pin" :size="13" /> {{ $o->store->market?->name }}{{ $o->store->stall ? ' · stall ' . $o->store->stall : '' }} · delivery in {{ $o->lead_days }} day{{ $o->lead_days === 1 ? '' : 's' }} · min. order {{ Money::qty($o->moq_milli) }} {{ $current->unit }}</div>
                                <div class="sm"><span class="p-stock {{ $o->stock_milli <= 10000 ? 'low' : '' }}">{{ Money::qty($o->stock_milli) }} {{ $current->unit }}</span>@if (isset($inBasket[$o->id])) <span class="muted">· {{ Money::qty($inBasket[$o->id]) }} in basket</span>@endif</div></div>
                            <div class="o-price"><b class="mono">{{ Money::format($o->price_cents, $o->currency) }}</b><span class="muted sm">/ {{ $current->unit }}</span>@if ($more > 0)<span class="sm bad">+{{ $more }}%</span>@endif</div>
                            @if ($canBuy)<div class="o-buy"><input class="input mono oq" inputmode="decimal" wire:model="qty.{{ $o->id }}" placeholder="{{ Money::qtyInput($o->moq_milli) }}" aria-label="Quantity from {{ $o->store->name }}"><button type="button" class="btn btn-sm btn-p" wire:click="add({{ $o->id }})" wire:loading.attr="disabled" wire:target="add({{ $o->id }})"><x-icon name="plus" :size="14" />{{ isset($inBasket[$o->id]) ? 'Update' : 'Add' }}</button></div>@endif
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($canBuy)<x-slot:foot><span class="sp"></span><a class="btn btn-p" href="{{ route('market.basket') }}" wire:navigate><x-icon name="box" />Open basket</a></x-slot:foot> @endif
        </x-drawer>
    @endif
</div>
