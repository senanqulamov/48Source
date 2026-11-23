@php use App\Support\Money; use App\Models\PurchaseOrder; $at = array_search($po->status, PurchaseOrder::FLOW, true); $sourcing = auth()->user()->isSourcing(); @endphp
<div>
    <x-ph :title="$sourcing ? $po->supplier->name : $po->rfq->title" :crumbs="[['Purchase orders', route('orders.index')], [$po->number]]" :recent="$po->number" :recent-sub="$po->supplier->name" :pager="$pager">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $po->number }}</span><x-badge :status="$po->status" :label="$po->statusLabel()" /><span class="tag">{{ $po->currency }}</span></div></x-slot:pre>
        <a class="btn" href="{{ route('orders.pdf', $po) }}" target="_blank" rel="noopener"><x-icon name="download" />PDF</a>
        @if (auth()->user()->isSourcing() || auth()->user()->role === 'supplier')<button type="button" class="btn" wire:click="open('message')"><x-icon name="mail" />Message</button>@endif
        @if ($sourcing && in_array($po->status, ['delivered', 'invoiced']))@perm('contracts', 'C')<button type="button" class="btn" wire:click="toContract" wire:confirm="Start a draft framework contract with {{ $po->supplier->name }} from this order's items and prices?"><x-icon name="clip" />Make contract</button>@endperm @endif
        @if ($canCancel)<button type="button" class="btn btn-d" wire:click="open('cancel')">Cancel order</button>@endif
        @if ($next)<button type="button" class="btn btn-p" wire:click="advance" wire:loading.attr="disabled"><x-icon name="check" />{{ $next['label'] }}</button>@endif
    </x-ph>

    <x-facts :items="[
        ['Order total', Money::format($po->total_cents, $po->currency), '', true],
        [$sourcing ? 'Supplier' : 'Buyer', $sourcing ? $po->supplier->name : $po->buyer->name],
        ['Issued', $po->issued_at?->format('j M Y') ?? 'Not issued'],
        ['Delivery due', $po->expected_at?->format('j M Y') ?? 'Not set', $po->expected_at && in_array($po->status, ['issued', 'acknowledged', 'in_transit']) ? '· ' . $po->expected_at->diffForHumans(['parts' => 1]) : ''],
        ['Related RFQ', $po->rfq->number, '', true],
    ]" />

    @if ($po->status !== 'cancelled')
        <x-life label="Order progress" :steps="array_map(fn ($k) => PurchaseOrder::STATUSES[$k], PurchaseOrder::FLOW)" :at="$at" :final="$at === count(PurchaseOrder::FLOW) - 1" :next="$waiting">
            <a class="btn btn-sm" href="{{ $sourcing ? route('rfqs.show', $po->rfq) : route('quotes.form', $po->rfq) }}" wire:navigate>Open {{ $po->rfq->number }}</a>
        </x-life>
    @else
        <div style="margin-bottom:16px"><x-note kind="bad"><b>Cancelled.</b> {{ $waiting }}</x-note></div>
    @endif

    <div class="grid g-2-1">
        <x-card title="Items" :sub="$po->items->count() . ' line(s)'" :flush="true">
            <div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Unit price</th><th class="num">Line total</th></tr></thead><tbody>
                @foreach ($po->items as $i)<tr><td class="t1">{{ $i->name }}</td><td class="num mono">{{ Money::qty($i->quantity_milli) }} {{ $i->unit }}</td><td class="num mono">{{ Money::format($i->unit_price_cents, $po->currency) }}</td><td class="num mono b">{{ Money::format($i->total_cents, $po->currency) }}</td></tr>@endforeach
                <tr><td colspan="3" class="b">Order total</td><td class="num mono b" style="font-size:15px">{{ Money::format($po->total_cents, $po->currency) }}</td></tr>
            </tbody></table></div>
        </x-card>
        <div class="stack">
            <x-card title="Terms"><dl class="kv" style="grid-template-columns:140px 1fr">
                <dt>Supplier</dt><dd>{{ $po->supplier->name }}</dd><dt>Payment terms</dt><dd>{{ $po->payment_terms ?: '-' }}</dd><dt>Deliver to</dt><dd>{{ $po->delivery_location ?: '-' }}</dd><dt>Expected</dt><dd>{{ $po->expected_at?->format('j M Y') ?? '-' }}</dd><dt>Currency</dt><dd>{{ $po->currency }}</dd>
                @if ($sourcing && $po->award_justification)<dt>Award note</dt><dd>{{ $po->award_justification }}</dd>@endif
            </dl></x-card>
            <x-card title="History"><x-history :logs="$logs" /></x-card>
        </div>
    </div>

    <div style="margin-top:16px"><x-card title="Receipts and invoices" sub="Goods received and the invoices checked against them">
        <x-slot:act>
            @if ($canReceive)<button type="button" class="btn btn-sm" wire:click="openReceipt"><x-icon name="box" :size="14" />Record receipt</button>@endif
            @if ($canInvoice)<button type="button" class="btn btn-sm btn-p" wire:click="openInvoice"><x-icon name="file" :size="14" />Send invoice</button>@endif
        </x-slot:act>
        <div class="row" style="margin-bottom:12px;flex-wrap:wrap"><x-badge :status="$pct >= 100 ? 'ok' : ($pct ? 'warn' : 'draft')" :label="$pct >= 100 ? 'Fully received' : ($pct ? 'Partly received' : 'Not received')" /><div class="row grow" style="min-width:200px"><span class="mono">{{ $pct }}%</span><div class="prog {{ $pct >= 100 ? 'ok' : '' }}" style="flex:1"><i style="width:{{ $pct }}%"></i></div></div></div>
        <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Item</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Still to come</th><th>Progress</th></tr></thead><tbody>
            @foreach ($po->items as $i)
                @php $g = $received[$i->id] ?? 0; $ip = $i->quantity_milli ? (int) min(100, intdiv($g * 100, $i->quantity_milli)) : 0; @endphp
                <tr><td class="t1">{{ $i->name }}</td><td class="num mono">{{ Money::qty($i->quantity_milli) }} <span class="faint">{{ $i->unit }}</span></td><td class="num mono b">{{ Money::qty($g) }}</td><td class="num mono">{{ Money::qty(max(0, $i->quantity_milli - $g)) }}</td><td><div style="min-width:110px"><div class="prog {{ $ip >= 100 ? 'ok' : '' }}"><i style="width:{{ $ip }}%"></i></div></div></td></tr>
            @endforeach
        </tbody></table></div>
        @if ($grns->isNotEmpty())
            <h3 class="lbl" style="margin:16px 0 8px">Receipts</h3>
            <ul class="list" style="border:1px solid var(--line);border-radius:12px">@foreach ($grns as $g)<li><div class="grow"><div class="t1 mono">{{ $g->number }}</div><div class="muted sm">{{ $g->received_on->format('j M Y') }} · {{ $g->receiver->name }} · {{ $g->items->count() }} line(s){{ $g->note ? ' · ' . $g->note : '' }}</div></div>@if ($g->items->sum('rejected_milli'))<x-badge status="warn" label="Some rejected" />@endif</li>@endforeach</ul>
        @endif
        <h3 class="lbl" style="margin:16px 0 8px">Invoices</h3>
        @if ($invoices->isEmpty())<p class="faint">No invoices on this order yet.</p>
        @else<ul class="list" style="border:1px solid var(--line);border-radius:12px">@foreach ($invoices as $v)<li class="click" data-href="{{ route('invoices.show', $v) }}" tabindex="0" style="cursor:pointer"><div class="grow"><div class="t1">Invoice <span class="mono">{{ $v->supplier_ref }}</span></div><div class="muted sm"><span class="mono">{{ $v->number }}</span> · {{ $v->issued_on->format('j M Y') }}</div></div><b class="mono">{{ Money::format($v->total_cents, $v->currency) }}</b><x-badge :status="['matched' => 'ok', 'disputed' => 'bad', 'approved' => 'info', 'paid' => 'ok', 'rejected' => 'bad'][$v->status] ?? 'info'" :label="$v->statusLabel()" /><x-icon name="right" /></li>@endforeach</ul>@endif
    </x-card></div>

    @if ($modal === 'receipt')
        <x-modal title="Record a goods receipt" :wide="true">
            <p class="muted" style="margin-bottom:12px">Enter what arrived in good order. Rejected quantities are noted and do not count as received.</p>
            <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Item</th><th class="num">Still to come</th><th style="width:130px">Accepted</th><th style="width:130px">Rejected</th></tr></thead><tbody>
                @foreach ($po->items as $i)<tr><td class="t1">{{ $i->name }}</td><td class="num mono">{{ Money::qty(max(0, $i->quantity_milli - ($received[$i->id] ?? 0))) }} <span class="faint">{{ $i->unit }}</span></td><td><input class="input mono" inputmode="decimal" wire:model="acc.{{ $i->id }}" aria-label="Accepted quantity of {{ $i->name }}"></td><td><input class="input mono" inputmode="decimal" wire:model="rej.{{ $i->id }}" aria-label="Rejected quantity of {{ $i->name }}" placeholder="0"></td></tr>@endforeach
            </tbody></table></div>
            <div class="fgrid" style="margin-top:14px"><x-field label="Received on" for="grn-d"><input id="grn-d" type="date" class="input" max="{{ now()->format('Y-m-d') }}" wire:model="rdate"></x-field><x-field label="Note" for="grn-n" :opt="true"><input id="grn-n" class="input" wire:model="rnote" placeholder="Delivery note number, condition"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="receive" wire:loading.attr="disabled">Record receipt</button></x-slot:foot>
        </x-modal>
    @elseif ($modal === 'invoice')
        <x-modal title="Send an invoice" :wide="true">
            <div class="fgrid" style="margin-bottom:14px"><x-field label="Your invoice number" for="iv-ref"><input id="iv-ref" class="input mono" wire:model="inv.supplier_ref" autofocus></x-field><x-field label="Invoice date" for="iv-d"><input id="iv-d" type="date" class="input" max="{{ now()->format('Y-m-d') }}" wire:model="inv.issued_on"></x-field><x-field label="Due date" for="iv-due" :opt="true"><input id="iv-due" type="date" class="input" wire:model="inv.due_on"></x-field></div>
            <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Item</th><th class="num">Received</th><th style="width:130px">Quantity</th><th style="width:150px">Unit price</th></tr></thead><tbody>
                @foreach ($po->items as $i)<tr><td class="t1">{{ $i->name }}</td><td class="num mono">{{ Money::qty($received[$i->id] ?? 0) }} <span class="faint">{{ $i->unit }}</span></td><td><input class="input mono" inputmode="decimal" wire:model="il.{{ $i->id }}.quantity" aria-label="Invoiced quantity of {{ $i->name }}"></td><td><input class="input mono" inputmode="decimal" wire:model="il.{{ $i->id }}.price" aria-label="Unit price of {{ $i->name }}"></td></tr>@endforeach
            </tbody></table></div>
            <p class="help" style="margin-top:10px">The invoice is checked against the order prices and the quantities received. Differences are shown to the buyer.</p>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="sendInvoice" wire:loading.attr="disabled">Send invoice</button></x-slot:foot>
        </x-modal>
    @endif

    @if ($modal === 'message')
        <x-modal title="Message about {{ $po->number }}"><x-field :label="$sourcing ? 'To ' . $po->supplier->name : 'To the buyer'" for="po-msg"><textarea id="po-msg" class="textarea" rows="4" wire:model="msg" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="sendMessage" wire:loading.attr="disabled"><x-icon name="send" />Send</button></x-slot:foot></x-modal>
    @endif
    @if ($modal === 'cancel')
        <x-modal title="Cancel this order?">
            <p style="margin-bottom:12px"><b class="mono">{{ $po->number }}</b> is cancelled{{ $po->status === 'issued' ? ' and the supplier is told' : '' }}. This cannot be undone.</p>
            <x-field label="Reason" for="creason"><textarea id="creason" class="textarea" rows="3" wire:model="reason" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" wire:click="close">Keep the order</button><button type="button" class="btn btn-d solid" wire:click="cancel">Cancel order</button></x-slot:foot>
        </x-modal>
    @endif
</div>
