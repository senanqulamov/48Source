<div>
    @php
        $u = auth()->user(); $h = (int) now()->format('G'); $ready = $strip[1]['v'];
        $sc = [];
        if ($u->allowed('rfqs', 'C')) { $sc[] = [route('rfqs.create'), 'plus', 'Create an RFQ', 'Start a request and invite suppliers']; }
        if ($u->allowed('quotes')) { $sc[] = [route('compare.index'), 'cols', 'Compare quotations', $ready . ' request' . ($ready === 1 ? '' : 's') . ' ready for a decision']; }
        if ($u->allowed('orders')) { $sc[] = [route('orders.index'), 'box', $u->isAdmin() ? 'Track orders' : 'Track my orders', 'From issue to delivery']; }
        if ($u->allowed('suppliers')) { $sc[] = [route('suppliers.index'), 'building', 'Find a supplier', \App\Models\Supplier::where('status', 'active')->count() . ' active suppliers in the directory']; }
    @endphp
    <x-ph :title="($h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening')) . ', ' . \Illuminate\Support\Str::before($u->name, ' ')" :desc="'You have ' . $ready . ' request' . ($ready === 1 ? '' : 's') . ' waiting for an award decision and ' . $closing . ' closing this week.'">
        @perm('rfqs', 'C')<a class="btn btn-p" href="{{ route('rfqs.create') }}" wire:navigate><x-icon name="plus" />Create RFQ</a>@endperm
    </x-ph>
    @if ($pending)
        <div style="margin-bottom:16px"><x-note kind="warn"><b>{{ $pending }} supplier{{ $pending === 1 ? '' : 's' }} waiting for approval.</b> <a class="link" href="{{ route('suppliers.index', ['status' => 'pending']) }}" wire:navigate>Review now</a></x-note></div>
    @endif
    <x-shortcuts :items="$sc" />
    <x-strip :items="$strip" />
    <x-viz :viz="$viz" />
    <div class="grid g-2-1">
        <div class="stack">
            <x-card title="Requires attention" sub="Most urgent first" :flush="true">
                @if ($attention->isEmpty())
                    <x-empty icon="check" title="Nothing needs you right now" text="Deadlines, comparisons and orders to issue will appear here." />
                @else
                    <ul class="list">
                        @foreach ($attention as $a)
                            <li><a class="row grow" href="{{ $a['url'] }}" wire:navigate style="color:inherit;gap:12px"><x-badge :status="$a['sev']" :label="['bad' => 'Urgent', 'warn' => 'Soon', 'info' => 'To do'][$a['sev']]" /><div class="grow" style="min-width:0"><div class="t1">{{ $a['title'] }}</div><div class="muted sm"><span class="mono">{{ $a['ref'] }}</span> · {{ $a['why'] }}</div></div><x-icon name="right" :size="14" /></a></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-card title="Latest requests" :flush="true">
                <x-slot:act><a class="btn btn-sm btn-g" href="{{ route('rfqs.index') }}" wire:navigate>All requests</a></x-slot:act>
                @if ($recent->isEmpty())
                    <x-empty icon="file" title="No requests yet" text="Create your first request for quotation to ask suppliers for prices.">@perm('rfqs', 'C')<a class="btn btn-p" href="{{ route('rfqs.create') }}" wire:navigate>New RFQ</a>@endperm</x-empty>
                @else
                    <div class="tbl-wrap"><table class="tbl compact"><thead><tr><th>Request</th><th>Status</th><th>Deadline</th><th class="num">Quotes</th></tr></thead><tbody>
                        @foreach ($recent as $r)
                            <tr class="click" data-href="{{ route('rfqs.show', $r) }}"><td><div class="t1">{{ $r->title }}</div><div class="t2"><span class="mono">{{ $r->number }}</span>@if ($r->category) · {{ $r->category->name }}@endif</div></td><td><x-badge :status="$r->status" :label="$r->statusLabel()" /></td><td>{{ $r->deadline_at?->format('j M Y') ?? '-' }}</td><td class="num mono">{{ $r->live_quotes_count }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>
        </div>
        <x-card title="Recent activity" :sub="auth()->user()->isAdmin() ? 'Everyone' : 'Your actions'"><x-history :logs="$logs" /></x-card>
    </div>
</div>
