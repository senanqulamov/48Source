<div>
    <x-ph :title="$supplier->name" desc="Invitations to quote, your quotations and the orders you have won." />
    @if ($supplier->status !== 'active')
        <div style="margin-bottom:16px"><x-note :kind="$supplier->status === 'pending' ? 'warn' : 'bad'">
            @if ($supplier->status === 'pending')<b>Your account is waiting for approval.</b> An administrator reviews new suppliers before they can receive invitations or send quotations.
            @else<b>Your supplier account is {{ mb_strtolower($supplier->statusLabel()) }}.</b> You cannot respond to requests. {{ $supplier->status_reason }}@endif
        </x-note></div>
    @endif
    <x-strip :items="$strip" />
    <x-viz :viz="$viz" side="sell" />
    <div class="grid g-2">
        <x-card title="Requests to answer" sub="Soonest deadline first" :flush="true">
            @if ($todo->isEmpty())
                <x-empty icon="check" title="Nothing waiting for you" text="New invitations appear here as soon as a buyer publishes a request." />
            @else
                <ul class="list">@foreach ($todo as $r)
                    <li><a class="row grow" href="{{ route('quotes.form', $r) }}" wire:navigate style="color:inherit;gap:12px"><div class="grow" style="min-width:0"><div class="t1">{{ $r->title }}</div><div class="muted sm"><span class="mono">{{ $r->number }}</span> · closes {{ $r->deadline_at->diffForHumans() }}</div></div><span class="btn btn-sm btn-p">Quote</span></a></li>
                @endforeach</ul>
            @endif
        </x-card>
        <x-card title="Latest orders" :flush="true">
            @if ($orders->isEmpty())
                <x-empty icon="award" title="No orders yet" text="When you win a request, the purchase order appears here." />
            @else
                <ul class="list">@foreach ($orders as $p)
                    <li><a class="row grow" href="{{ route('orders.show', $p) }}" wire:navigate style="color:inherit;gap:12px"><div class="grow" style="min-width:0"><div class="t1">{{ $p->rfq->title }}</div><div class="muted sm"><span class="mono">{{ $p->number }}</span></div></div><b class="mono">{{ \App\Support\Money::format($p->total_cents, $p->currency, false) }}</b><x-badge :status="$p->status" :label="$p->statusLabel()" /></a></li>
                @endforeach</ul>
            @endif
        </x-card>
    </div>
</div>
