@php
    use App\Models\Rfq;
    $admin = auth()->user()->isAdmin();
    $quick = ['' => ['All', $counts->sum()]];
    foreach (Rfq::STATUSES as $k => $l) { if (($counts[$k] ?? 0) || in_array($k, ['open', 'closed', 'awarded'])) { $quick[$k] = [$l, $counts[$k] ?? 0]; } }
    $facets = ['type' => ['Type', Rfq::TYPES]] + ($admin ? ['buyer' => ['Buyer', $buyers]] : []) + ['cat' => ['Category', $cats]];
@endphp
<div>
    <x-ph :title="$admin ? 'Requests for quotation' : 'My RFQs'" :desc="$admin ? 'Every request in the organisation, from draft to award.' : 'Requests you own, from draft to award.'" :crumbs="[[$admin ? 'RFQs' : 'My RFQs']]">
        @perm('rfqs', 'C')<a class="btn btn-p" href="{{ route('rfqs.create') }}" wire:navigate><x-icon name="plus" />New RFQ</a>@endperm
    </x-ph>
    <x-dt :lw="$this" :rows="$rows" id="rfq" search="Search reference, title, buyer or category" label="Search RFQs" noun="requests" :guide="['draft' => ['Draft', 'Being prepared. Suppliers cannot see it.'], 'open' => ['Open', 'Published. Suppliers can quote until the deadline.'], 'closed' => ['Closed', 'Quoting has ended. Compare the quotations and award.'], 'awarded' => ['Awarded', 'A supplier was chosen and a purchase order exists.'], 'cancelled' => ['Cancelled', 'Stopped by the buyer. Nothing more can happen.']]" :all="$all" :quick="$quick" :facets="$facets"
          :cols="['number' => 'Reference', 'buyer' => 'Buyer', 'type' => 'Type', 'items_count' => 'Items', 'live_quotes_count' => 'Quotes', 'created_at' => 'Created']" :keys="$rows->pluck('number')" :export="true">
        <x-slot:empty>
            <x-empty icon="file" title="No requests yet" text="Create a request to start collecting quotations.">@perm('rfqs', 'C')<a class="btn btn-p" href="{{ route('rfqs.create') }}" wire:navigate>New RFQ</a>@endperm</x-empty>
        </x-slot:empty>
        <x-slot:head>
            <x-th col="number" cls="hide-sm">Reference</x-th><x-th col="title">Title</x-th><th class="hide-md" data-c="buyer">Buyer</th><x-th col="type" cls="hide-lg">Type</x-th>
            <x-th col="status" cls="hide-sm">Status</x-th><x-th col="deadline_at">Deadline</x-th><x-th col="items_count" cls="hide-md" :num="true">Items</x-th>
            <x-th col="live_quotes_count" cls="hide-sm" :num="true">Quotes</x-th><x-th col="created_at" :sort="$sort ?: 'created_at'" cls="hide-lg">Created</x-th>
        </x-slot:head>
        @foreach ($rows as $r)
            @php $soon = $r->status === 'open' && $r->deadline_at && $r->deadline_at->lt(now()->addDays(3)); @endphp
            <tr wire:key="r-{{ $r->id }}" class="click {{ in_array($r->number, $selected, true) ? 'sel' : '' }}" data-href="{{ route('rfqs.show', $r) }}" tabindex="0">
                <td class="hide-sm"><input type="checkbox" class="chk" value="{{ $r->number }}" wire:model.live="selected" aria-label="Select row"></td>
                <td class="hide-sm" data-c="number"><span class="mono" style="white-space:nowrap">{{ $r->number }}</span></td>
                <td class="tmain"><div class="t1">{{ $r->title }}</div><div class="t2"><span class="only-sm-i mono">{{ $r->number }} · </span>{{ $r->category?->name ?? 'No category' }}</div></td>
                <td class="hide-md" data-c="buyer">{{ $r->buyer->name }}</td>
                <td class="hide-lg" data-c="type"><span class="tag">{{ Rfq::TYPES[$r->type] ?? $r->type }}</span></td>
                <td class="hide-sm"><x-badge :status="$r->status" :label="$r->statusLabel()" /></td>
                <td style="white-space:nowrap">@if ($r->deadline_at){{ $r->deadline_at->format('j M') }}<div class="t2" @if ($soon) style="color:var(--bad)" @endif>{{ $r->status === 'open' ? $r->deadline_at->diffForHumans(['parts' => 1]) : $r->deadline_at->format('Y') }}</div>@else<span class="faint">Not set</span>@endif</td>
                <td class="hide-md num mono" data-c="items_count">{{ $r->items_count }}</td>
                <td class="hide-sm num mono" data-c="live_quotes_count">{{ $r->live_quotes_count }}<span class="faint">/{{ $r->invitations_count }}</span></td>
                <td class="hide-lg" data-c="created_at" style="white-space:nowrap">{{ $r->created_at->format('j M') }}</td>
            </tr>
        @endforeach
        <x-slot:bulk>
            <button type="button" class="bb-btn" wire:click="export"><x-icon name="download" :size="15" />Export</button>
            @perm('rfqs', 'E')<button type="button" class="bb-btn" wire:click="closeSelected" wire:confirm="Close the selected open requests to quotations?"><x-icon name="lock" :size="15" />Close quoting</button>@endperm
        </x-slot:bulk>
    </x-dt>
</div>
