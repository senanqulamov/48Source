@php $bv = ['open' => 'info', 'closed' => 'warn']; $answered = $rfi->responses->where('status', 'answered'); @endphp
<div>
    <x-ph :title="$rfi->title" :crumbs="[['Information requests', route('rfis.index')], [$rfi->number]]" :recent="$rfi->number" :recent-sub="$rfi->title">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $rfi->number }}</span><x-badge :status="$bv[$rfi->status] ?? 'draft'" :label="$rfi->statusLabel()" /></div></x-slot:pre>
        @if ($sourcing)
            @if ($rfi->status === 'draft')@perm('rfqs', 'E')<a class="btn" href="{{ route('rfis.edit', $rfi) }}" wire:navigate><x-icon name="edit" />Edit</a><button type="button" class="btn btn-p" wire:click="publish"><x-icon name="send" />Send to suppliers</button>@endperm
            @else
                @if ($rfi->status === 'open')@perm('rfqs', 'E')<button type="button" class="btn" wire:click="closeNow" wire:confirm="Close this information request? Suppliers can no longer answer.">Close now</button>@endperm @endif
                @if ($rfi->rfq)<a class="btn" href="{{ route('rfqs.show', $rfi->rfq) }}" wire:navigate>Open {{ $rfi->rfq->number }}</a>
                @elseif ($answered->count())@perm('rfqs', 'C')<button type="button" class="btn btn-p" wire:click="toRfq" wire:loading.attr="disabled"><x-icon name="file" />Create RFQ from answers</button>@endperm @endif
            @endif
        @endif
    </x-ph>
    <x-facts :items="[['Answer by', $rfi->deadline_at?->format('j M Y, H:i') ?? 'Not set', $rfi->isOpen() ? '· ' . $rfi->deadline_at->diffForHumans(['parts' => 1]) : ''], ['Asked by', $rfi->buyer->name], ['Questions', count($rfi->questions), '', true]] + ($sourcing ? [3 => ['Answers', $answered->count() . ' of ' . $rfi->responses->count(), '', true]] : [])" />
    @if ($rfi->description)<div style="margin-bottom:16px"><x-card title="Background"><p style="max-width:75ch;white-space:pre-line">{{ $rfi->description }}</p></x-card></div>@endif
    @if ($sourcing)
        @if ($answered->isEmpty())
            <x-card title="Questions" :flush="true"><ul class="list">@foreach ($rfi->questions as $i => $q)<li><span class="mono muted" style="width:22px">{{ $i + 1 }}</span><div class="grow t1">{{ $q }}</div></li>@endforeach</ul>
                <div class="card-b muted" style="border-top:1px solid var(--line)">{{ $rfi->status === 'draft' ? 'Not sent yet.' : 'No supplier has answered yet. ' . $rfi->responses->count() . ' asked.' }}</div></x-card>
        @else
            <section class="card"><div class="toolbar"><span class="lbl">{{ $answered->count() }} of {{ $rfi->responses->count() }} suppliers answered</span><span class="sp"></span><span class="muted sm">Scroll sideways to compare</span></div>
                <div class="cmp-wrap" aria-label="Answers side by side"><table class="cmp"><thead><tr><th class="rh"><div class="b">Question</div></th>@foreach ($answered as $r)<th scope="col"><span class="sname">{{ $r->supplier->name }}</span><div class="t2 muted sm">answered {{ $r->answered_at->format('j M Y') }}</div></th>@endforeach</tr></thead><tbody>
                    @foreach ($rfi->questions as $i => $q)<tr><th scope="row" class="rh">{{ $q }}</th>@foreach ($answered as $r)<td style="white-space:pre-line;max-width:340px">{{ $r->answers[$i] ?? '-' }}</td>@endforeach</tr>@endforeach
                </tbody></table></div></section>
            @if ($rfi->responses->where('status', '!=', 'answered')->count())<p class="muted sm" style="margin-top:10px">Not answered yet: {{ $rfi->responses->where('status', '!=', 'answered')->pluck('supplier.name')->join(', ') }}.</p>@endif
        @endif
    @else
        <x-card title="Your answers" :sub="$mine?->status === 'answered' ? 'Sent ' . $mine->answered_at->format('j M Y, H:i') . ' · you can change them until the deadline' : 'Answer every question'">
            <div class="stack" style="gap:16px">
                @foreach ($rfi->questions as $i => $q)
                    <x-field :label="($i + 1) . '. ' . $q" :for="'an-' . $i">@if ($rfi->isOpen())<textarea id="an-{{ $i }}" class="textarea" rows="2" wire:model="answers.{{ $i }}"></textarea>@else<p style="white-space:pre-line">{{ $answers[$i] ?: 'Not answered.' }}</p>@endif</x-field>
                @endforeach
            </div>
            @if ($rfi->isOpen())<div class="row" style="margin-top:16px"><span class="sp"></span>@perm('quotes', 'E')<button type="button" class="btn btn-p" wire:click="answer" wire:loading.attr="disabled"><x-icon name="send" />{{ $mine?->status === 'answered' ? 'Send changed answers' : 'Send answers' }}</button>@endperm</div>
            @else<div style="margin-top:14px"><x-note kind="info">This information request is closed.</x-note></div>@endif
        </x-card>
    @endif
</div>
