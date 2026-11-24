@php use App\Support\Money; $n = $rfq->items->count(); $sent = $quote && $quote->status === 'submitted'; @endphp
<div>
    <x-ph :title="$rfq->title" :crumbs="[['Invitations & quotes', route('quotes.invitations')], [$rfq->number]]" :desc="'From ' . $rfq->buyer->name . ' · ' . ($rfq->category?->name ?? 'No category') . ' · quote by ' . $rfq->deadline_at->format('j M Y, H:i')">
        <x-slot:pre><div class="row" style="margin-bottom:6px"><span class="mono muted">{{ $rfq->number }}</span><x-badge :status="$rfq->status" :label="$rfq->statusLabel()" />@if ($quote && $quote->status !== 'draft')<x-badge :status="$quote->status" :label="'Your quotation: ' . $quote->statusLabel()" />@endif</div></x-slot:pre>
    </x-ph>

    @if (! $supplier->isActive())
        <div style="margin-bottom:16px"><x-note kind="warn"><b>Your supplier account is {{ mb_strtolower($supplier->statusLabel()) }}.</b> You can read this request but cannot quote on it.</x-note></div>
    @elseif ($inv?->status === 'declined')
        <div style="margin-bottom:16px"><x-note kind="warn">You declined this invitation. <a class="link" href="{{ route('quotes.invitations', ['tab' => 'closed']) }}" wire:navigate>Rejoin it</a> to quote.</x-note></div>
    @elseif ($bafo)
        <div style="margin-bottom:16px"><x-note kind="info"><b>Best and final offer, round {{ $bafo->round }}.</b> You can revise your quotation until {{ $bafo->deadline_at->format('j M Y, H:i') }} ({{ $bafo->deadline_at->diffForHumans(['parts' => 1]) }}).{{ $bafo->message ? ' ' . $bafo->message : '' }}</x-note></div>
    @elseif (! $rfq->isOpenForQuotes())
        <div style="margin-bottom:16px"><x-note kind="info">This request is no longer open for quotations.@if ($sent) Your quotation is with the buyer.@endif</x-note></div>
    @elseif ($sent)
        <div style="margin-bottom:16px"><x-note kind="ok">Your quotation (revision {{ $quote->revision }}) was submitted {{ $quote->submitted_at->diffForHumans() }}. You can revise it until {{ $rfq->deadline_at->format('j M Y, H:i') }}.</x-note></div>
    @endif

    <div class="grid g-2-1">
        <div class="stack">
            <x-card title="Your prices" :sub="$editable ? 'Leave a price empty if you do not offer that item' : null" :flush="true">
                <div class="tbl-wrap"><table class="tbl"><thead><tr><th style="width:40px">#</th><th>Item</th><th class="num">Quantity</th><th style="width:170px">Unit price, {{ $currency }}</th><th class="num">Line total</th></tr></thead><tbody>
                    @foreach ($rfq->items as $item)
                        <tr wire:key="qi-{{ $item->id }}"><td class="mono muted">{{ $item->position }}</td><td class="t1">{{ $item->name }}</td><td class="num mono">{{ $item->qty() }} {{ $item->unit }}</td>
                            <td>@if ($editable)<input class="input mono" inputmode="decimal" wire:model.live.debounce.400ms="prices.{{ $item->id }}" placeholder="0.00" aria-label="Unit price for {{ $item->name }}">@else<span class="mono">{{ ($prices[$item->id] ?? '') !== '' ? $prices[$item->id] : 'Not quoted' }}</span>@endif</td>
                            <td class="num mono b">{{ $lines[$item->id] !== null ? Money::format($lines[$item->id], $currency) : '-' }}</td></tr>
                    @endforeach
                    <tr><td colspan="4" class="b">Total for {{ $priced }} of {{ $n }} item(s)</td><td class="num mono b" style="font-size:15px">{{ Money::format($total, $currency) }}</td></tr>
                </tbody></table></div>
            </x-card>
            <x-card title="Terms">
                <div class="fgrid">
                    <x-field label="Currency" for="qcur"><select id="qcur" class="select" wire:model.live="currency" @disabled(! $editable)>@foreach (\App\Models\Currency::codes() as $c)<option>{{ $c }}</option>@endforeach</select></x-field>
                    <x-field label="Delivery time, days" for="qdays" help="From the order date."><input id="qdays" class="input mono" inputmode="numeric" wire:model="delivery_days" @disabled(! $editable)></x-field>
                    <x-field label="Payment terms" for="qterms" :opt="true"><input id="qterms" class="input" wire:model="payment_terms" placeholder="Net 30" @disabled(! $editable)></x-field>
                    <x-field label="Quotation valid until" for="qvalid"><input id="qvalid" class="input" type="date" wire:model="valid_until" @disabled(! $editable)></x-field>
                    <x-field label="Notes for the buyer" for="qnotes" :full="true" :opt="true"><textarea id="qnotes" class="textarea" rows="3" wire:model="notes" @disabled(! $editable)></textarea></x-field>
                </div>
            </x-card>
        </div>
        <div class="stack">
            <x-card title="Summary">
                <dl class="kv" style="grid-template-columns:140px 1fr"><dt>Items priced</dt><dd class="mono">{{ $priced }} of {{ $n }}</dd><dt>Total</dt><dd class="mono b" style="font-size:18px">{{ Money::format($total, $currency) }}</dd><dt>Deadline</dt><dd>{{ $rfq->deadline_at->diffForHumans() }}</dd></dl>
                @if ($editable)
                    @if ($priced > 0 && $priced < $n)<div style="margin-top:12px"><x-note kind="warn">You price {{ $priced }} of {{ $n }} items. A partial quotation can win those items only if the buyer awards item by item.</x-note></div>@endif
                    @if ($currency !== $rfq->currency)<div style="margin-top:12px"><x-note kind="warn">The buyer asked for {{ $rfq->currency }}. Quotations in different currencies cannot be compared.</x-note></div>@endif
                    <button type="button" class="btn btn-p" style="width:100%;justify-content:center;height:44px;margin-top:14px" wire:click="submit" wire:loading.attr="disabled" @disabled($priced === 0)><x-icon name="send" />{{ $sent ? 'Submit revision' : 'Submit quotation' }}</button>
                @endif
            </x-card>
            <x-card title="About this request"><dl class="kv" style="grid-template-columns:140px 1fr">
                <dt>Delivery to</dt><dd>{{ $rfq->delivery_location ?: '-' }}</dd><dt>Needed by</dt><dd>{{ $rfq->required_by?->format('j M Y') ?? '-' }}</dd><dt>Payment terms</dt><dd>{{ $rfq->payment_terms ?: '-' }}</dd><dt>Currency</dt><dd>{{ $rfq->currency }}</dd></dl>
                @if ($rfq->description)<p class="muted" style="margin-top:10px;white-space:pre-line">{{ $rfq->description }}</p>@endif
                @if ($rfq->requirements)<h3 class="lbl" style="margin:12px 0 4px">Requirements</h3><p style="white-space:pre-line">{{ $rfq->requirements }}</p>@endif
            </x-card>
        </div>
    </div>
    @if ($auction)
        <div style="margin-top:16px"><x-note :kind="$auction->isLive() ? 'warn' : 'info'"><b>{{ $auction->isLive() ? 'A reverse auction is running on this request.' : 'A reverse auction was held on this request.' }}</b> <a class="link" href="{{ route('rfqs.auction', $rfq) }}" wire:navigate>{{ $auction->isLive() ? 'Go to the auction' : 'See the result' }}</a></x-note></div>
    @endif
    <div class="grid g-2" style="margin-top:16px">
        <x-card title="Files from the buyer" sub="Specifications, drawings and terms" :flush="true">
            @if ($rfqFiles->isEmpty())<div class="card-b faint">The buyer attached no files.</div>
            @else<ul class="list">@foreach ($rfqFiles as $f)<li><x-icon name="paperclip" /><div class="grow" style="min-width:0"><div class="t1 trunc">{{ $f->name }}</div><div class="muted sm">{{ $f->sizeLabel() }}</div></div><a class="btn btn-sm btn-g" href="{{ route('files.show', $f->id) }}"><x-icon name="download" :size="14" />Download</a></li>@endforeach</ul>@endif
        </x-card>
        <x-card title="Files with your quotation" sub="Datasheets, certificates, your own offer document" :flush="true">
            @if ($myFiles->isEmpty())<div class="card-b faint">{{ $quote && $quote->status !== 'draft' ? 'No files attached.' : 'Send your quotation first, then attach files to it.' }}</div>
            @else<ul class="list">@foreach ($myFiles as $f)<li wire:key="mf-{{ $f->id }}"><x-icon name="paperclip" /><div class="grow" style="min-width:0"><div class="t1 trunc">{{ $f->name }}</div><div class="muted sm">{{ $f->sizeLabel() }}</div></div><a class="btn btn-sm btn-g" href="{{ route('files.show', $f->id) }}">Open file</a>@if ($editable)<button type="button" class="icon-btn" wire:click="removeFile({{ $f->id }})" aria-label="Remove {{ $f->name }}"><x-icon name="trash" :size="15" /></button>@endif</li>@endforeach</ul>@endif
            @if ($editable && $quote && $quote->status === 'submitted')<div class="card-b" style="border-top:1px solid var(--line)"><input type="file" class="input" style="padding:8px" wire:model="upload" aria-label="Attach a file to your quotation"><div wire:loading wire:target="upload" class="muted sm">Uploading...</div>@error('upload')<span class="errt">{{ $message }}</span>@enderror</div>@endif
        </x-card>
    </div>
    @if ($quote?->original_total_cents)<div style="margin-top:16px"><x-note kind="warn"><b>Your total was adjusted</b> by the buyer's administrator from {{ \App\Support\Money::format($quote->original_total_cents, $quote->currency) }} to {{ \App\Support\Money::format($quote->total_cents, $quote->currency) }}. Reason: {{ $quote->adjust_reason }}. If this is not what you agreed, send a message to the buyer.</x-note></div>@endif
    <div style="margin-top:16px"><x-card title="Questions and answers" sub="Answers are shown to every invited supplier, without saying who asked" :flush="true">
        @if ($qa->isEmpty())<div class="card-b faint">No questions yet.</div>
        @else<ul class="list">@foreach ($qa as $q)<li style="display:block"><div class="t1">{{ $q->question }}</div>@if ($q->answer)<div style="margin-top:4px">{{ $q->answer }}</div><div class="muted sm">Answered {{ $q->answered_at->format('j M Y, H:i') }}</div>@else<div class="muted sm">Your question · waiting for the buyer's answer</div>@endif</li>@endforeach</ul>@endif
        @if ($rfq->isOpenForQuotes() && $supplier->isActive())
            <div class="card-b" style="border-top:1px solid var(--line)"><x-field label="Ask the buyer" for="qa-q" help="Ask about scope, quantities or terms. Do not include your prices."><textarea id="qa-q" class="textarea" rows="2" wire:model="question"></textarea></x-field>
                <div class="row" style="margin-top:10px"><span class="sp"></span><button type="button" class="btn" wire:click="ask" wire:loading.attr="disabled"><x-icon name="send" />Send question</button></div></div>
        @endif
    </x-card></div>
</div>
