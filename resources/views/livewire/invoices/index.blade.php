@php
    use App\Models\Invoice; use App\Support\Money;
    $bv = ['submitted' => 'info', 'matched' => 'ok', 'disputed' => 'bad', 'approved' => 'info', 'paid' => 'ok', 'rejected' => 'bad'];
    $quick = ['' => ['All', $counts->sum()]]; foreach (Invoice::STATUSES as $k => $l) { if ($counts[$k] ?? 0) { $quick[$k] = [$l, $counts[$k]]; } }
@endphp
<div>
    <x-ph title="Invoices & receipts" :desc="$supplierSide ? 'Invoices you sent and how they were checked.' : 'Every invoice is checked against the order and what was received before it can be paid.'" :crumbs="[['Invoices & receipts']]" />
    <x-tabs :on="$tab" :tabs="['invoices' => ['Invoices', $all], 'receipts' => ['Goods receipts', $receiptCount]]" />
    @if ($tab === 'invoices')
        <x-dt :lw="$this" :rows="$rows" id="inv" search="Search invoice, order or supplier" label="Search invoices" noun="invoices" :guide="['ok' => ['Matched', 'Agrees with the order and what was received.'], 'bad' => ['Does not match', 'Price or quantity differs. Check before approving.'], 'info' => ['Approved for payment', 'Checked and released for payment.'], 'won' => ['Paid', 'Payment was made.'], 'lost' => ['Rejected', 'Sent back to the supplier with a reason.']]" :all="$all" :quick="$quick" :export="true">
            <x-slot:empty><x-empty icon="file" title="No invoices yet" :text="$supplierSide ? 'Send an invoice from a purchase order once goods are delivered.' : 'Invoices appear here when suppliers send them on an order.'"><a class="btn" href="{{ route('orders.index') }}" wire:navigate>Open purchase orders</a></x-empty></x-slot:empty>
            <x-slot:head><x-th col="number" cls="hide-sm">Reference</x-th><th>{{ $supplierSide ? 'Order' : 'Supplier' }}</th><x-th col="status">Match</x-th><x-th col="total_cents" :num="true">Amount</x-th><x-th col="issued_on" cls="hide-md">Invoice date</x-th><x-th col="due_on" cls="hide-sm">Due</x-th></x-slot:head>
            @foreach ($rows as $i)
                @php $late = $i->due_on && in_array($i->status, ['matched', 'disputed', 'approved']) && $i->due_on->isPast(); @endphp
                <tr wire:key="inv-{{ $i->id }}" class="click" data-href="{{ route('invoices.show', $i) }}" tabindex="0">
                    <td class="hide-sm"><span class="mono" style="white-space:nowrap">{{ $i->number }}</span></td>
                    <td class="tmain"><div class="t1">{{ $supplierSide ? $i->order->number : $i->supplier->name }}</div><div class="t2">Invoice no. <span class="mono">{{ $i->supplier_ref }}</span>@unless ($supplierSide) · <span class="mono">{{ $i->order->number }}</span>@endunless</div></td>
                    <td><x-badge :status="$bv[$i->status]" :label="$i->statusLabel()" /></td><td class="num mono b">{{ Money::format($i->total_cents, $i->currency) }}</td>
                    <td class="hide-md" style="white-space:nowrap">{{ $i->issued_on->format('j M Y') }}</td><td class="hide-sm" style="white-space:nowrap">@if ($i->due_on){{ $i->due_on->format('j M Y') }}@if ($late)<div class="t2" style="color:var(--bad)">overdue</div>@endif @else - @endif</td>
                </tr>
            @endforeach
            <x-slot:foot>{{ Money::format($open, 'USD') }} not yet paid</x-slot:foot>
        </x-dt>
    @else
        <x-card :flush="true">
            @if ($receipts->isEmpty())<x-empty icon="box" title="No goods receipts yet" text="Receipts are recorded on a purchase order when goods arrive." />
            @else
                <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Receipt</th><th>Order</th><th>Received on</th><th class="hide-sm">Recorded by</th><th class="num">Lines</th><th class="hide-md">Note</th></tr></thead><tbody>
                    @foreach ($receipts as $g)
                        <tr class="click" data-href="{{ route('orders.show', $g->order) }}" tabindex="0"><td class="mono b">{{ $g->number }}</td><td class="tmain"><div class="t1 mono">{{ $g->order->number }}</div><div class="t2">{{ $g->order->supplier->name }}</div></td><td style="white-space:nowrap">{{ $g->received_on->format('j M Y') }}</td><td class="hide-sm">{{ $g->receiver->name }}</td>
                            <td class="num mono">{{ $g->items->count() }}@if ($g->items->sum('rejected_milli')) <x-badge status="warn" label="Rejects" class="plain" />@endif</td><td class="hide-md muted">{{ $g->note ?: '-' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </x-card>
    @endif
</div>
