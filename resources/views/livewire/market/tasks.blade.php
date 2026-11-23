@php use App\Support\Money; @endphp
<div>
    <x-ph title="Tasks" desc="Pick the goods for confirmed orders, then deliver them. Finishing a task moves the order on and tells the buyer." :crumbs="[['Tasks']]" />
    <x-tabs :on="$tab" :tabs="['open' => ['To do', $open], 'done' => ['Done', $doneCount]]" />
    @if ($tasks->isEmpty())
        <x-card><x-empty icon="check" :title="$tab === 'open' ? 'Nothing to do right now' : 'No finished tasks yet'" text="Tasks appear when the store confirms an order." /></x-card>
    @else
        <div class="stack" style="gap:10px">
            @foreach ($tasks as $t)
                <details class="qd" wire:key="t-{{ $t->id }}" @if ($loop->first && $tab === 'open') open @endif><summary>
                    <span style="flex:1;min-width:160px"><b>{{ $t->type === 'pick' ? 'Pick the goods' : 'Deliver to the buyer' }}</b> <span class="badge b-{{ $t->type === 'pick' ? 'info' : 'warn' }} plain">{{ $t->type === 'pick' ? 'Pick' : 'Deliver' }}</span>
                        <span class="muted sm" style="display:block"><span class="mono">{{ $t->order->number }}</span> · {{ $t->order->supplier?->name ?? $t->order->buyer->name }} · {{ $t->order->items->count() }} line(s){{ $t->type === 'deliver' ? ' · to ' . $t->order->deliver_to : '' }}</span></span>
                    @if ($t->status === 'open')@perm('marketplace', 'E')<button type="button" class="btn btn-sm btn-p" wire:click="done({{ $t->id }})" wire:loading.attr="disabled"><x-icon name="check" :size="14" />{{ $t->type === 'pick' ? 'Picked' : 'Delivered' }}</button>@endperm
                    @else<span class="muted sm">{{ $t->doer?->name }} · {{ $t->done_at?->format('j M, H:i') }}</span>@endif
                    <span class="cv"><x-icon name="down" /></span></summary>
                    <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Product</th><th>SKU</th><th class="num">Quantity</th></tr></thead><tbody>@foreach ($t->order->items as $l)<tr><td class="t1">{{ $l->name }}</td><td class="mono">{{ $l->sku }}</td><td class="num mono b">{{ Money::qty($l->quantity_milli) }} <span class="faint">{{ $l->unit }}</span></td></tr>@endforeach</tbody></table></div>
                    @if ($t->order->note)<div class="sm muted" style="padding:10px 16px;border-top:1px solid var(--line)">Note from the buyer: {{ $t->order->note }}</div>@endif
                </details>
            @endforeach
        </div>
    @endif
</div>
