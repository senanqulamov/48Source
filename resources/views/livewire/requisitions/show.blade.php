@php use App\Support\Money; $at = ['draft' => 0, 'pending' => 1, 'approved' => 2, 'converted' => 3][$req->status] ?? 1; $bv = ['pending' => 'warn', 'approved' => 'ok', 'rejected' => 'bad', 'converted' => 'info', 'cancelled' => 'bad']; @endphp
<div>
    <x-ph :title="$req->title" :crumbs="[['Requisitions', route('requisitions.index')], [$req->number]]" :recent="$req->number" :recent-sub="$req->title">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $req->number }}</span><x-badge :status="$bv[$req->status] ?? 'draft'" :label="$req->statusLabel()" />@if ($req->category)<span class="tag">{{ $req->category->name }}</span>@endif</div></x-slot:pre>
        @if ($req->status === 'draft' && $mine)@perm('requisitions', 'E')<a class="btn" href="{{ route('requisitions.edit', $req) }}" wire:navigate><x-icon name="edit" />Edit</a>@endperm<button type="button" class="btn btn-p" wire:click="submit" wire:loading.attr="disabled"><x-icon name="send" />Submit for approval</button>@endif
        @if ($canDecide)<button type="button" class="btn btn-d" wire:click="open('reject')">Reject</button><button type="button" class="btn btn-p" wire:click="open('approve')"><x-icon name="check" />Approve</button>@endif
        @if ($req->status === 'approved')@perm('rfqs', 'C')<button type="button" class="btn btn-p" wire:click="convert" wire:loading.attr="disabled"><x-icon name="file" />Create RFQ</button>@endperm @endif
        @if (in_array($req->status, ['draft', 'pending']) && $mine)<button type="button" class="btn btn-d" wire:click="open('cancel')">Cancel</button>@endif
    </x-ph>
    <x-facts :items="[['Estimated total', $req->total_cents ? Money::format($req->total_cents, $req->currency) : 'No prices given', '', true], ['Requester', $req->requester->name], ['Needed by', $req->needed_by?->format('j M Y') ?? 'Not set'], ['Items', $req->items->count(), '', true], ['Submitted', $req->submitted_at?->format('j M Y') ?? 'Not yet']]" />
    @if (in_array($req->status, ['rejected', 'cancelled']))
        <div style="margin-bottom:16px"><x-note kind="bad"><b>{{ $req->statusLabel() }}.</b> {{ $req->approvals->firstWhere('status', 'rejected')?->comment }}</x-note></div>
    @else
        <x-life label="Requisition progress" :steps="['Draft', 'Approval', 'Approved', 'Request for quotation']" :at="$at" :final="$req->status === 'converted'">
            <x-slot:next>
                @switch($req->status)
                    @case('draft') Next step: submit it for approval. @break
                    @case('pending') Now: waiting for {{ $req->approvals->firstWhere('status', 'pending')?->label }}. @break
                    @case('approved') Next step: create the request for quotation from it. @break
                    @case('converted') Done: became {{ $req->rfq?->number }}. @break
                @endswitch
            </x-slot:next>
            @if ($req->rfq && auth()->user()->allowed('rfqs'))<a class="btn btn-sm" href="{{ route('rfqs.show', $req->rfq) }}" wire:navigate>Open {{ $req->rfq->number }}</a>@endif
        </x-life>
    @endif
    <div class="grid g-2-1">
        <div class="stack">
            @if ($req->justification)<x-card title="Why it is needed"><p style="max-width:75ch;white-space:pre-line">{{ $req->justification }}</p></x-card>@endif
            <x-card title="Items" :flush="true"><div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Est. unit price</th><th class="num">Est. total</th></tr></thead><tbody>
                @foreach ($req->items as $it)<tr><td class="t1">{{ $it->name }}</td><td class="num mono">{{ Money::qty($it->quantity_milli) }} <span class="faint">{{ $it->unit }}</span></td><td class="num mono">{{ $it->est_price_cents !== null ? Money::format($it->est_price_cents, $req->currency) : '-' }}</td><td class="num mono b">{{ $it->est_price_cents !== null ? Money::format($it->total_cents, $req->currency) : '-' }}</td></tr>@endforeach
                <tr><td class="b" colspan="3">Estimated total</td><td class="num mono b">{{ Money::format($req->total_cents, $req->currency) }}</td></tr>
            </tbody></table></div></x-card>
        </div>
        <div class="stack">
            <x-card title="Approval chain" :sub="$req->approvals->isEmpty() ? 'Built when the requisition is submitted' : null">
                @if ($req->approvals->isEmpty())<p class="faint">Not submitted yet.</p>
                @else
                    <ol class="steps-v">
                        @foreach ($req->approvals as $a)
                            <li class="{{ $a->status === 'approved' ? 'done' : ($a->status === 'pending' ? 'now' : ($a->status === 'rejected' ? 'bad' : '')) }}"><b>{{ $a->label }}</b>
                                <div class="muted sm">@if ($a->approver){{ ucfirst($a->status) }} by {{ $a->approver->name }} · {{ $a->decided_at->format('j M Y, H:i') }}@elseif ($a->status === 'pending')Waiting for a {{ mb_strtolower(\App\Support\Perm::ROLES[$a->role]) }}@else Not reached yet @endif</div>
                                @if ($a->comment)<div class="sm" style="margin-top:2px">"{{ $a->comment }}"</div>@endif</li>
                        @endforeach
                    </ol>
                @endif
            </x-card>
            <x-card title="History"><x-history :logs="$logs" /></x-card>
        </div>
    </div>
    @if ($modal === 'approve')
        <x-modal title="Approve this requisition?"><p style="margin-bottom:12px">You approve <b class="mono">{{ $req->number }}</b> for {{ Money::format($req->total_cents, $req->currency) }}.</p>
            <x-field label="Comment" for="ap-c" :opt="true"><textarea id="ap-c" class="textarea" rows="2" wire:model="comment"></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Not yet</button><button type="button" class="btn btn-p" wire:click="decide(true)">Approve</button></x-slot:foot></x-modal>
    @elseif ($modal === 'reject')
        <x-modal title="Reject this requisition?"><x-field label="Reason" for="rj-c" help="The requester sees this."><textarea id="rj-c" class="textarea" rows="3" wire:model="comment" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Keep it</button><button type="button" class="btn btn-d solid" wire:click="decide(false)">Reject</button></x-slot:foot></x-modal>
    @elseif ($modal === 'cancel')
        <x-modal title="Cancel this requisition?"><p><b class="mono">{{ $req->number }}</b> stops here. This cannot be undone.</p>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Keep it</button><button type="button" class="btn btn-d solid" wire:click="cancel">Cancel requisition</button></x-slot:foot></x-modal>
    @endif
</div>
