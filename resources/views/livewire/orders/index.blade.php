@php use App\Support\Money; @endphp
<div>
    <x-ph title="Purchase orders" :desc="$supplierSide ? 'Orders you have won. Acknowledge them, dispatch, then invoice.' : 'An award becomes a purchase order. Issue it, then follow it to delivery and invoice.'" :crumbs="[['Purchase orders']]" />
    @php
        $quick = ['' => ['All', $counts->sum()]];
        foreach (\App\Models\PurchaseOrder::STATUSES as $k => $l) { if (! ($supplierSide && $k === 'draft') && (($counts[$k] ?? 0) || in_array($k, ['issued', 'in_transit', 'delivered']))) { $quick[$k] = [$l, $counts[$k] ?? 0]; } }
    @endphp
    <x-dt :lw="$this" :rows="$rows" id="po" search="Search order, request or {{ $supplierSide ? 'buyer' : 'supplier' }}" label="Search purchase orders" noun="orders" :guide="['draft' => ['Draft', 'Created by the award. Not yet sent to the supplier.'], 'issued' => ['Issued', 'Sent. Waiting for the supplier to acknowledge.'], 'acknowledged' => ['Acknowledged', 'The supplier accepted the order.'], 'in_transit' => ['In transit', 'Goods are on the way. Record receipts as they arrive.'], 'delivered' => ['Delivered', 'Everything arrived. Waiting for the invoice.'], 'invoiced' => ['Invoiced', 'The supplier sent an invoice.'], 'cancelled' => ['Cancelled', 'Stopped before delivery.']]" :all="$counts->sum()" :quick="$quick"
          :facets="$supplierSide ? [] : ['sup' => ['Supplier', $sups]]" :cols="['rfq' => 'Related RFQ', 'buyer' => 'Buyer', 'created_at' => 'Issued', 'expected_at' => 'Delivery due']" :export="true">
        <x-slot:empty><x-empty icon="award" title="No purchase orders yet" :text="$supplierSide ? 'When you win a request, the order appears here.' : 'Award a request to create the first order.'" /></x-slot:empty>
        <x-slot:head>
            <x-th col="number">Order</x-th><th>{{ $supplierSide ? 'Buyer' : 'Supplier' }}</th><th class="hide-md" data-c="rfq">Related RFQ</th><th class="hide-lg" data-c="buyer">{{ $supplierSide ? 'Contact' : 'Buyer' }}</th>
            <x-th col="total_cents" :num="true">Amount</x-th><x-th col="created_at" :sort="$sort ?: 'created_at'" cls="hide-sm">Issued</x-th><x-th col="expected_at" cls="hide-md">Delivery due</x-th><x-th col="status">Status</x-th>
        </x-slot:head>
        @foreach ($rows as $p)
            <tr wire:key="p-{{ $p->id }}" class="click" data-href="{{ route('orders.show', $p) }}" tabindex="0">
                <td class="mono b" style="white-space:nowrap">{{ $p->number }}</td>
                <td class="tmain"><div class="t1">{{ $supplierSide ? config('app.org', 'Buyer organisation') : $p->supplier->name }}</div><div class="t2 trunc">{{ $p->rfq->title }}</div></td>
                <td class="hide-md mono" data-c="rfq">{{ $p->rfq->number }}</td><td class="hide-lg" data-c="buyer">{{ $p->buyer->name }}</td>
                <td class="num mono b">{{ Money::format($p->total_cents, $p->currency) }}</td>
                <td class="hide-sm" data-c="created_at" style="white-space:nowrap">{{ $p->created_at->format('j M Y') }}</td><td class="hide-md" data-c="expected_at" style="white-space:nowrap">{{ $p->expected_at?->format('j M Y') ?? '-' }}</td>
                <td><x-badge :status="$p->status" :label="$p->statusLabel()" /></td>
            </tr>
        @endforeach
        <x-slot:foot>{{ Money::format($pageTotal, 'USD') }} on this page</x-slot:foot>
    </x-dt>
</div>
