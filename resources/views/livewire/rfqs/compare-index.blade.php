<div>
    <x-ph title="Quotation comparison" desc="Pick a request to compare its quotations side by side. Closed requests are ready for an award decision." :crumbs="[['Quotation comparison']]" />
    <x-dt :lw="$this" :rows="$rows" :all="$all" id="compare" search="Search request or RFQ number" label="Search comparison requests" noun="requests" :quick="['' => ['All', $all], 'closed' => ['Ready to compare', (int) ($counts['closed'] ?? 0)], 'open' => ['Open', (int) ($counts['open'] ?? 0)], 'awarded' => ['Awarded', (int) ($counts['awarded'] ?? 0)]]">
        <x-slot:head><x-th col="number">RFQ</x-th><x-th col="status">Status</x-th><x-th col="quotes" :num="true">Quotes</x-th><th class="num hide-sm">Lowest total</th><th class="num hide-md">Price spread</th><x-th col="deadline" cls="hide-sm">Award due</x-th><th></th></x-slot:head>
        @forelse ($rows as $r)
            @php
                $complete = $r->liveQuotes->filter(fn ($q) => $q->total_cents > 0);
                $lowest = $complete->min('total_cents'); $highest = $complete->max('total_cents');
                $currency = $complete->first()?->currency ?? $r->currency;
                $spread = $lowest && $highest > $lowest ? number_format((($highest - $lowest) * 100) / $lowest, 1) : null;
                $awardDue = $r->closed_at?->copy()->addDays(5);
            @endphp
            <tr class="click" data-href="{{ route('rfqs.compare', $r) }}" tabindex="0">
                <td><div class="t1">{{ $r->title }}</div><div class="t2 mono">{{ $r->number }}</div></td>
                <td><x-badge :status="$r->status" /></td><td class="num mono">{{ $r->live_quotes_count }}</td>
                <td class="num mono hide-sm">{{ $lowest ? \App\Support\Money::format($lowest, $currency) : '-' }}</td>
                <td class="num mono hide-md">{{ $spread ? '+'.$spread.'%' : '-' }}</td>
                <td class="hide-sm">@if ($r->status === 'closed' && $awardDue){{ $awardDue->format('j M Y') }} <x-badge :status="$awardDue->isPast() ? 'bad' : 'warn'" :label="$awardDue->isPast() ? 'Overdue' : 'Due'" /><div class="t2">Closed {{ $r->closed_at->diffForHumans(['parts' => 1]) }}</div>@else<span class="faint">-</span>@endif</td>
                <td class="num"><a class="btn btn-sm btn-p" href="{{ route('rfqs.compare', $r) }}" wire:navigate>Compare</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty icon="cols" title="Nothing to compare yet" text="Closed requests with quotations will appear here. They can then be compared side by side." /></td></tr>
        @endforelse
    </x-dt>
</div>
