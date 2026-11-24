@php
    use App\Models\Requisition; use App\Support\Money;
    $quick = ['' => ['All', $counts->sum()]]; foreach (Requisition::STATUSES as $k => $l) { if (($counts[$k] ?? 0) || in_array($k, ['pending', 'approved'])) { $quick[$k] = [$l, $counts[$k] ?? 0]; } }
@endphp
<div>
    <x-ph title="Purchase requisitions" desc="Anyone can ask for something to be bought. The request is approved along a chain set by its amount, and then turned into a request for quotation." :crumbs="[['Requisitions']]">
        @perm('requisitions', 'C')<a class="btn btn-p" href="{{ route('requisitions.create') }}" wire:navigate><x-icon name="plus" />New requisition</a>@endperm
    </x-ph>
    <x-strip :items="$strip" />
    <x-dt :lw="$this" :rows="$rows" id="pr" search="Search reference, title or requester" label="Search requisitions" noun="requisitions" :guide="['draft' => ['Draft', 'Not yet submitted.'], 'warn' => ['Awaiting approval', 'In the approval chain.'], 'ok' => ['Approved', 'Can become a request for quotation.'], 'bad' => ['Rejected', 'An approver said no and gave a reason.'], 'info' => ['Converted to RFQ', 'A request for quotation was created from it.']]" :all="$all" :quick="$quick" :cols="['requester' => 'Requester', 'needed_by' => 'Needed by', 'created_at' => 'Created']" :export="true">
        <x-slot:empty><x-empty icon="inbox" title="No requisitions yet" text="Raise a requisition when a team needs something bought.">@perm('requisitions', 'C')<a class="btn btn-p" href="{{ route('requisitions.create') }}" wire:navigate>New requisition</a>@endperm</x-empty></x-slot:empty>
        <x-slot:head><x-th col="number" cls="hide-sm">Reference</x-th><x-th col="title">Title</x-th><th class="hide-md" data-c="requester">Requester</th><x-th col="status">Status</x-th><th class="hide-md">Approval</th><x-th col="total_cents" :num="true">Estimated</x-th><x-th col="needed_by" cls="hide-lg">Needed by</x-th><x-th col="created_at" :sort="$sort ?: 'created_at'" cls="hide-lg">Created</x-th></x-slot:head>
        @foreach ($rows as $r)
            @php $done = $r->approvals->where('status', 'approved')->count(); $steps = $r->approvals->count(); $cur = $r->approvals->firstWhere('status', 'pending'); @endphp
            <tr wire:key="pr-{{ $r->id }}" class="click" data-href="{{ route('requisitions.show', $r) }}" tabindex="0">
                <td class="hide-sm"><span class="mono" style="white-space:nowrap">{{ $r->number }}</span></td>
                <td class="tmain"><div class="t1">{{ $r->title }}</div><div class="t2">{{ $r->items_count }} item{{ $r->items_count === 1 ? '' : 's' }}</div></td>
                <td class="hide-md" data-c="requester">{{ $r->requester->name }}</td>
                <td><x-badge :status="['pending' => 'warn', 'approved' => 'ok', 'rejected' => 'bad', 'converted' => 'info', 'cancelled' => 'bad'][$r->status] ?? 'draft'" :label="$r->statusLabel()" /></td>
                <td class="hide-md">@if ($steps)<div class="mono sm">{{ $done }} of {{ $steps }}</div><div class="t2">{{ $cur ? 'Waiting: ' . $cur->label : ($r->status === 'rejected' ? 'Stopped' : 'Complete') }}</div>@else<span class="faint">Not submitted</span>@endif</td>
                <td class="num mono">{{ $r->total_cents ? Money::format($r->total_cents, $r->currency) : '-' }}</td>
                <td class="hide-lg" data-c="needed_by" style="white-space:nowrap">{{ $r->needed_by?->format('j M Y') ?? '-' }}</td><td class="hide-lg" data-c="created_at" style="white-space:nowrap">{{ $r->created_at->format('j M') }}</td>
            </tr>
        @endforeach
    </x-dt>
</div>
