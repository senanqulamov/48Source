@php use App\Support\Money; $bv = ['submitted' => 'info', 'matched' => 'ok', 'disputed' => 'bad', 'approved' => 'info', 'paid' => 'ok', 'rejected' => 'bad']; $open = in_array($inv->status, ['matched', 'disputed']); $at = ['submitted' => 0, 'matched' => 1, 'disputed' => 1, 'approved' => 2, 'paid' => 3][$inv->status] ?? 1; @endphp
<div>
    <x-ph :title="'Invoice ' . $inv->supplier_ref" :crumbs="[['Invoices & receipts', route('invoices.index')], [$inv->number]]" :recent="$inv->number" :recent-sub="$inv->supplier->name">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $inv->number }}</span><x-badge :status="$bv[$inv->status]" :label="$inv->statusLabel()" /><span class="tag">{{ $inv->supplier->name }}</span></div></x-slot:pre>
        <a class="btn" href="{{ route('invoices.pdf', $inv) }}" target="_blank" rel="noopener"><x-icon name="download" />PDF</a>
        @if ($buyerSide)
            @if ($open)@perm('invoices', 'E')<button type="button" class="btn" wire:click="rematch"><x-icon name="refresh" />Match again</button>@endperm @perm('invoices', 'A')<button type="button" class="btn btn-d" wire:click="open('reject')">Reject</button><button type="button" class="btn btn-p" wire:click="open('approve')"><x-icon name="check" />Approve for payment</button>@endperm
            @elseif ($inv->status === 'approved')@perm('invoices', 'A')<button type="button" class="btn btn-p" wire:click="pay" wire:confirm="Mark invoice {{ $inv->supplier_ref }} as paid?"><x-icon name="check" />Mark as paid</button>@endperm @endif
        @endif
    </x-ph>
    <x-facts :items="[['Invoice total', Money::format($inv->total_cents, $inv->currency), '', true], ['Purchase order', $po->number, '', true], ['Invoice date', $inv->issued_on->format('j M Y')], ['Due', $inv->due_on?->format('j M Y') ?? 'Not stated'], ['Sent by', $inv->submitter->name]]" />
    @if ($inv->status === 'rejected')<div style="margin-bottom:16px"><x-note kind="bad"><b>Rejected</b> by {{ $inv->decider?->name }} on {{ $inv->decided_at->format('j M Y') }}. {{ $inv->decision_note }}</x-note></div>
    @else
        <x-life label="Invoice progress" :steps="['Received', 'Three-way match', 'Approved', 'Paid']" :at="$at" :final="$inv->status === 'paid'">
            <x-slot:next>
                @switch($inv->status)
                    @case('matched') Matches the order and receipts. {{ $buyerSide ? 'Next step: approve it for payment.' : 'Waiting for the buyer to approve it.' }} @break
                    @case('disputed') {{ count($inv->issues ?? []) }} difference{{ count($inv->issues ?? []) === 1 ? '' : 's' }} found. {{ $buyerSide ? 'Record the missing receipt and match again, reject it, or approve with a reason.' : 'The buyer is checking it.' }} @break
                    @case('approved') Approved by {{ $inv->decider?->name }}. Waiting for payment. @break
                    @case('paid') Paid on {{ $inv->paid_at->format('j M Y') }}. @break
                @endswitch
            </x-slot:next>
            <a class="btn btn-sm" href="{{ route('orders.show', $po) }}" wire:navigate>Open {{ $po->number }}</a>
        </x-life>
    @endif
    @if ($inv->issues)<div style="margin-bottom:16px"><x-note kind="warn"><b>Differences found by the three-way match</b><ul style="margin:6px 0 0 18px">@foreach ($inv->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></x-note></div>@endif
    @if ($inv->decision_note && $inv->status !== 'rejected')<div style="margin-bottom:16px"><x-note kind="info"><b>Approval note:</b> {{ $inv->decision_note }}</x-note></div>@endif
    <x-card title="Three-way match" sub="Order, receipt and invoice side by side" :flush="true">
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Item</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Invoiced before</th><th class="num">This invoice</th><th class="num">Order price</th><th class="num">Invoice price</th><th class="num">Line total</th><th>Check</th></tr></thead><tbody>
            @foreach ($po->items as $it)
                @php $l = $lines[$it->id] ?? null; $prev = $before[$it->id] ?? 0; $got = $received[$it->id] ?? 0; $qtyOk = ! $l || $prev + $l->quantity_milli <= $got; $priceOk = ! $l || $l->unit_price_cents === $it->unit_price_cents; @endphp
                <tr><td class="t1">{{ $it->name }}</td><td class="num mono">{{ Money::qty($it->quantity_milli) }} <span class="faint">{{ $it->unit }}</span></td><td class="num mono">{{ Money::qty($got) }}</td><td class="num mono">{{ Money::qty($prev) }}</td>
                    <td class="num mono b" @unless ($qtyOk) style="color:var(--bad)" @endunless>{{ $l ? Money::qty($l->quantity_milli) : '-' }}</td><td class="num mono">{{ Money::format($it->unit_price_cents, $po->currency) }}</td>
                    <td class="num mono" @unless ($priceOk) style="color:var(--bad)" @endunless>{{ $l ? Money::format($l->unit_price_cents, $inv->currency) : '-' }}</td><td class="num mono b">{{ $l ? Money::format($l->total_cents, $inv->currency) : '-' }}</td>
                    <td>@if (! $l)<span class="faint">Not invoiced</span>@elseif ($qtyOk && $priceOk)<x-badge status="ok" label="Matches" />@else<x-badge status="bad" :label="! $priceOk ? 'Price differs' : 'Quantity differs'" />@endif</td></tr>
            @endforeach
            <tr><td class="b" colspan="7">Invoice total</td><td class="num mono b">{{ Money::format($inv->total_cents, $inv->currency) }}</td><td></td></tr>
        </tbody></table></div>
    </x-card>
    @if ($modal === 'approve')
        <x-modal title="Approve for payment?"><p style="margin-bottom:12px">Invoice <b class="mono">{{ $inv->supplier_ref }}</b> for <b class="mono">{{ Money::format($inv->total_cents, $inv->currency) }}</b>.</p>
            @if ($inv->status === 'disputed')<div style="margin-bottom:12px"><x-note kind="warn">This invoice does not match. Approving it anyway needs a reason, which is recorded.</x-note></div>@endif
            <x-field label="Note" for="iv-n" :opt="$inv->status !== 'disputed'"><textarea id="iv-n" class="textarea" rows="3" wire:model="note"></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Not yet</button><button type="button" class="btn btn-p" wire:click="approve">Approve for payment</button></x-slot:foot></x-modal>
    @elseif ($modal === 'reject')
        <x-modal title="Reject this invoice?"><x-field label="Reason" for="iv-r" help="The supplier sees this and can send a corrected invoice."><textarea id="iv-r" class="textarea" rows="3" wire:model="note" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Keep it</button><button type="button" class="btn btn-d solid" wire:click="reject">Reject invoice</button></x-slot:foot></x-modal>
    @endif
</div>
