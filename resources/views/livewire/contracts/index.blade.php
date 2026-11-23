@php
    use App\Models\Contract; use App\Support\Money; $src = auth()->user()->isSourcing();
    $quick = ['' => ['All', $counts->sum()]]; foreach (Contract::STATUSES as $k => $l) { if (($counts[$k] ?? 0) || $k === 'active') { $quick[$k] = [$l, $counts[$k] ?? 0]; } }
@endphp
<div>
    <x-ph title="Contracts" :desc="$src ? 'Framework agreements with fixed prices. Place release orders against them instead of running a new request each time.' : 'Agreements your company holds, and the release orders placed on them.'" :crumbs="[['Contracts']]">
        @if ($src)@perm('contracts', 'C')<a class="btn btn-p" href="{{ route('contracts.create') }}" wire:navigate><x-icon name="plus" />New contract</a>@endperm @endif
    </x-ph>
    <x-dt :lw="$this" :rows="$rows" id="ct" search="Search reference, title or supplier" label="Search contracts" noun="contracts" :all="$all" :quick="$quick" :export="true">
        <x-slot:empty><x-empty icon="clip" title="No contracts yet" :text="$src ? 'Create a contract to agree prices once and order many times.' : 'Contracts appear here when a buyer activates one with your company.'" /></x-slot:empty>
        <x-slot:head><x-th col="number" cls="hide-sm">Reference</x-th><x-th col="title">Contract</x-th><x-th col="status">Status</x-th><x-th col="ends_on">Period</x-th><x-th col="released_cents">Used</x-th></x-slot:head>
        @foreach ($rows as $c)
            @php $pct = $c->ceiling_cents ? (int) min(100, intdiv($c->released_cents * 100, $c->ceiling_cents)) : 0; $soon = $c->status === 'active' && $c->ends_on->lte(now()->addDays(30)); @endphp
            <tr wire:key="ct-{{ $c->id }}" class="click" data-href="{{ route('contracts.show', $c) }}" tabindex="0">
                <td class="hide-sm"><span class="mono" style="white-space:nowrap">{{ $c->number }}</span></td>
                <td class="tmain"><div class="t1">{{ $c->title }}</div><div class="t2">{{ $c->supplier->name }}</div></td>
                <td><x-badge :status="['active' => 'ok', 'expired' => 'warn', 'terminated' => 'bad'][$c->status] ?? 'draft'" :label="$c->statusLabel()" /></td>
                <td style="white-space:nowrap">{{ $c->starts_on->format('j M Y') }} - {{ $c->ends_on->format('j M Y') }}@if ($soon)<div class="t2" style="color:var(--bad)">ends {{ $c->ends_on->diffForHumans(['parts' => 1]) }}</div>@endif</td>
                <td style="min-width:170px"><div class="mono sm">{{ Money::format($c->released_cents, $c->currency, false) }}{{ $c->ceiling_cents ? ' of ' . Money::format($c->ceiling_cents, $c->currency, false) : ' · no ceiling' }}</div>@if ($c->ceiling_cents)<div class="prog {{ $pct >= 90 ? 'bad' : ($pct >= 70 ? 'warn' : 'ok') }}" style="margin-top:4px"><i style="width:{{ $pct }}%"></i></div>@endif</td>
            </tr>
        @endforeach
    </x-dt>
</div>
