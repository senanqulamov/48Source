@php use App\Support\Money; @endphp
<div>
    <x-ph title="Assistant" desc="Three helpers for everyday sourcing work. You stay in control: nothing is created, sent or awarded until you confirm it." :crumbs="[['Assistant']]" />
    <div style="margin-bottom:16px"><x-note kind="info">These helpers follow fixed rules over your own data. They are not an AI model and they do not send anything outside 48Source.</x-note></div>
    <div class="stack">
        <x-card title="Draft an RFQ from a sentence" sub="Write quantities, units and items the way you would say them">
            <x-field label="What do you need?" for="as-s" help='Example: "40 t rebar 16 mm, 200 sheets welded mesh and 15 pcs tie wire for the bridge deck, needed in 4 weeks"'><textarea id="as-s" class="textarea" rows="2" wire:model="sentence"></textarea></x-field>
            <div class="row" style="margin-top:10px"><span class="sp"></span><button type="button" class="btn" wire:click="propose" wire:loading.attr="disabled"><x-icon name="edit" />Propose a draft</button></div>
            @if ($draft)
                <div style="margin-top:14px;border-top:1px solid var(--line);padding-top:14px">
                    <div class="row wrap" style="margin-bottom:8px"><b>{{ $draft['title'] }}</b><span class="tag">deadline in {{ $draft['deadline_days'] }} days</span>@if ($draft['category_id'])<span class="tag">{{ $categories[$draft['category_id']] ?? 'Category' }}</span>@endif</div>
                    @if ($draft['items'])<div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Item</th><th class="num">Quantity</th><th>Unit</th></tr></thead><tbody>@foreach ($draft['items'] as $it)<tr><td class="t1">{{ $it['name'] }}</td><td class="num mono">{{ $it['quantity'] }}</td><td>{{ $it['unit'] }}</td></tr>@endforeach</tbody></table></div>@endif
                    @foreach ($draft['notes'] as $n)<p class="muted sm" style="margin-top:6px">{{ $n }}</p>@endforeach
                    @if ($draft['items'])<div class="row" style="margin-top:12px"><span class="muted sm">This saves a draft only. You choose suppliers and publish yourself.</span><span class="sp"></span>@perm('rfqs', 'C')<button type="button" class="btn btn-p" wire:click="createDraft" wire:loading.attr="disabled"><x-icon name="file" />Create this draft</button>@endperm</div>@endif
                </div>
            @endif
        </x-card>
        <x-card title="Review quotations" sub="A plain reading of the quotations on one request">
            <div style="max-width:560px"><x-field label="Request" for="as-r"><select id="as-r" class="select" wire:model.live="review"><option value="">Choose a closed request</option>@foreach ($withQuotes as $r)<option value="{{ $r->number }}">{{ $r->number }} - {{ $r->title }}</option>@endforeach</select></x-field></div>
            @if ($rfq && $rfq->pricesHidden())<div style="margin-top:12px"><x-note kind="warn">Quotations on this request are sealed until quoting closes.</x-note></div>
            @elseif ($findings)
                <div class="stack" style="gap:8px;margin-top:14px">@foreach ($findings as $f)<x-note :kind="$f['kind']">{{ $f['text'] }}</x-note>@endforeach</div>
                <div class="row" style="margin-top:12px"><span class="sp"></span><a class="btn" href="{{ route('rfqs.scenarios', $rfq) }}" wire:navigate>Award scenarios</a><a class="btn btn-p" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="cols" />Open comparison</a></div>
            @endif
        </x-card>
        @perm('suppliers')
        <x-card title="Suggest suppliers" sub="Active suppliers of a category, ranked by rating, response, orders won and risk">
            <div style="max-width:420px"><x-field label="Category" for="as-c"><select id="as-c" class="select" wire:model.live="category"><option value="">Choose a category</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field></div>
            @if ($suggested !== null)
                @if (! $suggested)<p class="faint" style="margin-top:12px">No active supplier in this category.</p>
                @else<ul class="list" style="margin-top:12px;border:1px solid var(--line);border-radius:12px">@foreach ($suggested as $k => $x)<li class="click" style="cursor:pointer" data-href="{{ route('suppliers.show', $x['supplier']->id) }}" tabindex="0"><span class="rk-n mono">{{ $k + 1 }}</span><div class="grow"><div class="t1">{{ $x['supplier']->name }}</div><div class="muted sm">{{ implode(' · ', $x['why']) }}</div></div><x-badge :status="['low' => 'ok', 'medium' => 'warn', 'high' => 'bad'][$x['risk']]" :label="ucfirst($x['risk']) . ' risk'" /><x-icon name="right" /></li>@endforeach</ul>@endif
            @endif
        </x-card>
        @endperm
    </div>
</div>
