@php use App\Models\Rfi; $bv = ['open' => 'info', 'closed' => 'warn']; $quick = ['' => ['All', $counts->sum()]]; foreach (Rfi::STATUSES as $k => $l) { if (($counts[$k] ?? 0) || $k === 'open') { $quick[$k] = [$l, $counts[$k] ?? 0]; } } @endphp
<div>
    <x-ph title="Information requests" :desc="$sourcing ? 'Ask suppliers questions before you write a request for quotation: capability, lead times, certificates.' : 'Questions from the buyer. Your answers help them decide who to invite to quote.'" :crumbs="[['Information requests']]">
        @if ($sourcing)@perm('rfqs', 'C')<a class="btn btn-p" href="{{ route('rfis.create') }}" wire:navigate><x-icon name="plus" />New information request</a>@endperm @endif
    </x-ph>
    <x-dt :lw="$this" :rows="$rows" id="rfi" search="Search reference or title" label="Search information requests" noun="information requests" :all="$all" :quick="$quick">
        <x-slot:empty><x-empty icon="info" title="No information requests yet" :text="$sourcing ? 'Use one to learn about the market before asking for prices.' : 'When a buyer asks your company a question, it appears here.'" /></x-slot:empty>
        <x-slot:head><x-th col="number" cls="hide-sm">Reference</x-th><x-th col="title">Title</x-th><x-th col="status">Status</x-th><x-th col="deadline_at">Deadline</x-th><th class="num">{{ $sourcing ? 'Answers' : 'Your answer' }}</th></x-slot:head>
        @foreach ($rows as $r)
            @php $ans = $r->responses->where('status', 'answered')->count(); @endphp
            <tr wire:key="rfi-{{ $r->id }}" class="click" data-href="{{ route('rfis.show', $r) }}" tabindex="0"><td class="hide-sm"><span class="mono" style="white-space:nowrap">{{ $r->number }}</span></td><td class="tmain"><div class="t1">{{ $r->title }}</div><div class="t2">{{ count($r->questions) }} question{{ count($r->questions) === 1 ? '' : 's' }} · {{ $r->buyer->name }}</div></td>
                <td><x-badge :status="$bv[$r->status] ?? 'draft'" :label="$r->statusLabel()" /></td><td style="white-space:nowrap">{{ $r->deadline_at?->format('j M Y') ?? '-' }}@if ($r->isOpen())<div class="t2">{{ $r->deadline_at->diffForHumans(['parts' => 1]) }}</div>@endif</td>
                <td class="num">@if ($sourcing)<span class="mono">{{ $ans }}<span class="faint">/{{ $r->responses->count() }}</span></span>@else<x-badge :status="$ans ? 'ok' : 'warn'" :label="$ans ? 'Sent' : 'Waiting'" />@endif</td></tr>
        @endforeach
    </x-dt>
</div>
