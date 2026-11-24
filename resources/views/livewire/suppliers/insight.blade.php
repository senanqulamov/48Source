<div>
    <x-ph title="Supplier 360" desc="Risk, documents, scorecards and open actions for every supplier. Open a supplier to see the full record." :crumbs="[['Supplier 360']]" />
    <x-strip :items="$strip" />
    <x-dt :lw="$this" :rows="$rows" id="s360" search="Search supplier or city" label="Search suppliers" noun="suppliers" :all="$all" :facets="['status' => ['Status', \App\Models\Supplier::STATUSES]]">
        <x-slot:empty><x-empty icon="shield" title="No suppliers yet" text="Suppliers appear here once they are added or register themselves." /></x-slot:empty>
        <x-slot:head><x-th col="name" :sort="$sort ?: 'name'">Supplier</x-th><x-th col="status">Status</x-th><th>Risk</th><th class="hide-sm">Documents</th><th class="hide-sm">Latest scorecard</th><th class="hide-md">Open actions</th><x-th col="rating_tenths" cls="hide-md">Rating</x-th></x-slot:head>
        @foreach ($rows as $s)
            @php
                $r = $risk[$s->id];
                $expired = $s->documents->filter->isExpired()->count();
                $soon = $s->documents->filter->expiresSoon()->count();
                $card = $s->scorecards->sortByDesc('period')->first();
                $open = $s->actions->where('status', 'open');
                $late = $open->filter(fn ($a) => $a->due_on->isPast() && ! $a->due_on->isToday())->count();
            @endphp
            <tr wire:key="s360-{{ $s->id }}">
                <td><a class="t1" href="{{ route('suppliers.show', $s->id) }}" wire:navigate>{{ $s->name }}</a><div class="muted sm">{{ $s->category?->name ?? 'No category' }}@if ($s->city) · {{ $s->city }}@endif</div></td>
                <td><x-badge :status="$s->status" :label="$s->statusLabel()" /></td>
                <td><span class="badge {{ ['high' => 'b-bad', 'medium' => 'b-warn', 'low' => 'b-ok'][$r['level']] }}" @if ($r['reasons']) data-tip="{{ implode(' ', $r['reasons']) }}" @endif>{{ ['high' => 'High risk', 'medium' => 'Medium risk', 'low' => 'Low risk'][$r['level']] }}</span></td>
                <td class="hide-sm">{{ $s->documents->count() }}@if ($expired) <span class="bad sm">· {{ $expired }} expired</span>@elseif ($soon) <span class="warn sm">· {{ $soon }} expiring</span>@endif</td>
                <td class="hide-sm">@if ($card)<span class="mono b">{{ number_format($card->score_tenths / 10, 1) }}</span> <span class="muted sm">{{ $card->period }}</span>@else<span class="faint">None</span>@endif</td>
                <td class="hide-md">{{ $open->count() }}@if ($late) <span class="bad sm">· {{ $late }} overdue</span>@endif</td>
                <td class="hide-md"><x-stars :tenths="$s->rating_tenths" /></td>
            </tr>
        @endforeach
    </x-dt>
</div>
