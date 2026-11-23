<div>
    <x-ph title="Search" :desc="$term === '' ? 'Type a request number, title, order number or supplier name.' : 'Results for “' . $term . '”'" :crumbs="[['Search']]" />
    <div class="tb-search" style="max-width:520px;margin-bottom:16px"><x-icon name="search" /><input type="search" wire:model.live.debounce.300ms="q" placeholder="Search requests, orders, suppliers…" aria-label="Search" autofocus></div>
    @if (mb_strlen($term) < 2)
        <x-card><x-empty icon="search" title="Start typing" text="At least two characters." /></x-card>
    @elseif ($rfqs->isEmpty() && $orders->isEmpty() && $suppliers->isEmpty() && $more->isEmpty())
        <x-card><x-empty icon="search" title="Nothing found" text="Check the spelling or try fewer words." /></x-card>
    @else
        <div class="stack">
            @foreach ($more as [$title, $items])<x-card :title="$title" :flush="true"><ul class="list">@foreach ($items as [$t1, $t2, $url])<li class="click" style="cursor:pointer" data-href="{{ $url }}" tabindex="0"><div class="grow"><div class="t1">{{ $t1 }}</div><div class="muted sm mono">{{ $t2 }}</div></div><x-icon name="right" /></li>@endforeach</ul></x-card>@endforeach
            @if ($rfqs->isNotEmpty())<x-card title="Requests" :flush="true"><ul class="list">@foreach ($rfqs as $r)
                <li><a class="row grow" style="color:inherit;gap:12px" href="{{ auth()->user()->isSourcing() ? route('rfqs.show', $r) : route('quotes.form', $r) }}" wire:navigate><div class="grow"><div class="t1">{{ $r->title }}</div><div class="muted sm mono">{{ $r->number }}</div></div><x-badge :status="$r->status" :label="$r->statusLabel()" /></a></li>
            @endforeach</ul></x-card>@endif
            @if ($orders->isNotEmpty())<x-card title="Purchase orders" :flush="true"><ul class="list">@foreach ($orders as $p)
                <li><a class="row grow" style="color:inherit;gap:12px" href="{{ route('orders.show', $p) }}" wire:navigate><div class="grow"><div class="t1">{{ $p->supplier->name }}</div><div class="muted sm"><span class="mono">{{ $p->number }}</span> · {{ $p->rfq->title }}</div></div><x-badge :status="$p->status" :label="$p->statusLabel()" /></a></li>
            @endforeach</ul></x-card>@endif
            @if ($suppliers->isNotEmpty())<x-card title="Suppliers" :flush="true"><ul class="list">@foreach ($suppliers as $s)
                <li><a class="row grow" style="color:inherit;gap:12px" href="{{ route('suppliers.index', ['q' => $s->name]) }}" wire:navigate><div class="grow"><div class="t1">{{ $s->name }}</div><div class="muted sm">{{ $s->city }}</div></div><x-badge :status="$s->status" :label="$s->statusLabel()" /></a></li>
            @endforeach</ul></x-card>@endif
        </div>
    @endif
</div>
