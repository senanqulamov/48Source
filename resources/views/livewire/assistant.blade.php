@php use App\Support\Money; @endphp
<div>
    <x-ph title="Assistant" desc="Two helpers for everyday sourcing work. You stay in control: nothing is created, sent or awarded until you confirm it." :crumbs="[['Assistant']]" />
    <div style="margin-bottom:16px"><x-note kind="info">These helpers follow fixed rules over your own data. They are not an AI model and they do not send anything outside 48Source.</x-note></div>
    <div class="grid g-2">
        <div class="stack">
            <x-card title="Draft an RFQ from a sentence" sub="Write quantities, units and items the way you would say them">
                <x-field label="Describe what you need" for="as-s" help="Mention quantities, the site, the time frame and the budget if you know them."><textarea id="as-s" class="textarea" rows="4" wire:model="sentence"></textarea></x-field>
                <div class="row" style="margin-top:10px"><button type="button" class="btn btn-p" wire:click="propose" wire:loading.attr="disabled"><x-icon name="edit" />Draft the request</button></div>
            </x-card>
            <x-card title="Review quotations" sub="A plain reading of the quotations on one request">
                <div style="max-width:560px"><x-field label="Request" for="as-r"><select id="as-r" class="select" wire:model.live="review"><option value="">Choose a request with quotations</option>@foreach ($withQuotes as $r)<option value="{{ $r->number }}">{{ $r->number }} - {{ $r->title }}</option>@endforeach</select></x-field></div>
                @if ($rfq && $rfq->pricesHidden())
                    <div style="margin-top:12px"><x-note kind="warn">Bids on this request are sealed until the deadline.</x-note></div>
                @elseif ($rfq)
                    <div class="row" style="margin-top:12px"><button type="button" class="btn btn-p" wire:click="summarise">Summarise quotations</button><button type="button" class="btn" wire:click="unusual">Find unusual prices</button></div>
                @endif
            </x-card>
        </div>
        <div>
            @if ($result === 'draft' && $draft)
                <x-card title="Suggested request" sub="Check it before you use it">
                    <dl class="kv"><dt>Title</dt><dd>{{ $draft['title'] }}</dd><dt>Category</dt><dd>{{ $draft['category_id'] ? ($categories[$draft['category_id']] ?? 'Category') : 'Not mentioned' }}</dd><dt>Quotation deadline</dt><dd>{{ now()->addDays($draft['deadline_days'])->format('j M Y') }}</dd><dt>Needed within</dt><dd>{{ $draft['needed_days'] }} days</dd><dt>Budget</dt><dd class="mono">{{ $draft['budget'] !== '' ? Money::format(Money::parse($draft['budget']), 'USD') : 'Not mentioned' }}</dd></dl>
                    <h3 class="lbl" style="margin:14px 0 6px">Items found</h3>
                    @if ($draft['items'])<ul class="list">@foreach ($draft['items'] as $it)<li><span class="grow">{{ $it['name'] }}</span><span class="mono b">{{ $it['quantity'] }} {{ $it['unit'] }}</span></li>@endforeach</ul>@else<p class="faint">No quantities found. Write them as "120 office chairs".</p>@endif
                    @foreach ($draft['notes'] as $n)<p class="muted sm" style="margin-top:6px">{{ $n }}</p>@endforeach
                    <div class="row" style="margin-top:14px">@perm('rfqs', 'C')<button type="button" class="btn btn-p" wire:click="openWizard" wire:loading.attr="disabled" @disabled(! $draft['items'])><x-icon name="file" />Open in the RFQ wizard</button>@endperm<span class="muted sm">Nothing is saved until you save it in the wizard.</span></div>
                </x-card>
            @elseif ($result === 'summary' && $rfq)
                <x-card :title="'Summary - ' . $rfq->number" sub="Based on the quotations as they stand now"><ul style="margin:0 0 0 18px;display:grid;gap:8px">@foreach ($findings as $finding)<li>{{ $finding['text'] }}</li>@endforeach</ul><div class="row" style="margin-top:14px"><a class="btn btn-p" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="cols" />Open comparison</a><a class="btn" href="{{ route('rfqs.scenarios', $rfq) }}" wire:navigate>Award scenarios</a></div></x-card>
            @elseif ($result === 'unusual' && $rfq)
                <x-card :title="'Unusual prices - ' . $rfq->number" sub="More than 20% away from the median of all quotations" :flush="true">@if ($flags)<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Supplier</th><th>Item</th><th class="num">Price</th><th class="num">Median</th><th class="num">Difference</th><th>What to check</th></tr></thead><tbody>@foreach ($flags as $flag)<tr><td>{{ $flag['supplier'] }}</td><td>{{ $flag['item'] }}</td><td class="num mono">{{ Money::format($flag['price'], $rfq->currency) }}</td><td class="num mono">{{ Money::format($flag['median'], $rfq->currency) }}</td><td class="num mono">{{ $flag['difference'] > 0 ? '+' : '' }}{{ $flag['difference'] }}%</td><td>{{ $flag['check'] }}</td></tr>@endforeach</tbody></table></div>@else<x-empty icon="check" title="No unusual prices" text="Every unit price is within 20% of the median, or fewer than three quotations price an item." />@endif</x-card>
            @else
                <x-card><x-empty icon="star" title="Results appear here" text="Pick one of the helpers on the left." /></x-card>
            @endif
        </div>
    </div>
</div>
