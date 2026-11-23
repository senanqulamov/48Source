<div>
    <x-ph title="Compare quotations" desc="Requests that are closed and waiting for your decision come first." :crumbs="[['Compare quotations']]" />
    <div class="stack">
        <x-card title="Ready to compare and award" :sub="$ready->count() . ' request(s)'" :flush="true">
            @if ($ready->isEmpty())<x-empty icon="check" title="Nothing waiting for a decision" text="A request appears here when it is closed and has at least one quotation." />
            @else<ul class="list">@foreach ($ready as $r)<li><a class="row grow" style="color:inherit;gap:12px" href="{{ route('rfqs.compare', $r) }}" wire:navigate><div class="grow"><div class="t1">{{ $r->title }}</div><div class="muted sm"><span class="mono">{{ $r->number }}</span> · closed {{ $r->closed_at?->diffForHumans() }}</div></div><span class="mono b">{{ $r->live_quotes_count }} quotation(s)</span><span class="btn btn-sm btn-p">Compare</span></a></li>@endforeach</ul>@endif
        </x-card>
        @if ($open->isNotEmpty())<x-card title="Still open" sub="Quotations are arriving" :flush="true"><ul class="list">@foreach ($open as $r)<li><a class="row grow" style="color:inherit;gap:12px" href="{{ route('rfqs.show', $r) }}" wire:navigate><div class="grow"><div class="t1">{{ $r->title }}</div><div class="muted sm"><span class="mono">{{ $r->number }}</span> · closes {{ $r->deadline_at->diffForHumans() }}</div></div><span class="mono">{{ $r->live_quotes_count }} so far</span></a></li>@endforeach</ul></x-card>@endif
        @if ($done->isNotEmpty())<x-card title="Recently awarded" :flush="true"><ul class="list">@foreach ($done as $r)<li><a class="row grow" style="color:inherit;gap:12px" href="{{ route('rfqs.compare', $r) }}" wire:navigate><div class="grow"><div class="t1">{{ $r->title }}</div><div class="muted sm"><span class="mono">{{ $r->number }}</span> · awarded {{ $r->awarded_at?->format('j M Y') }}</div></div><x-badge status="awarded" /></a></li>@endforeach</ul></x-card>@endif
    </div>
</div>
