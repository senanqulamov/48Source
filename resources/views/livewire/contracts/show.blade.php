@php use App\Support\Money; use App\Models\ContractRelease; $pct = $c->ceiling_cents ? (int) min(100, intdiv($c->released_cents * 100, $c->ceiling_cents)) : 0; $bv = ['active' => 'ok', 'expired' => 'warn', 'terminated' => 'bad']; @endphp
<div>
    <x-ph :title="$c->title" :crumbs="[['Contracts', route('contracts.index')], [$c->number]]" :recent="$c->number" :recent-sub="$c->title">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $c->number }}</span><x-badge :status="$bv[$c->status] ?? 'draft'" :label="$c->statusLabel()" /><span class="tag">{{ $c->supplier->name }}</span></div></x-slot:pre>
        @if ($sourcing)
            @if ($c->status === 'draft')@perm('contracts', 'E')<a class="btn" href="{{ route('contracts.edit', $c) }}" wire:navigate><x-icon name="edit" />Edit</a><button type="button" class="btn btn-p" wire:click="activate" wire:confirm="Activate this contract? The supplier is told and the price list is fixed."><x-icon name="check" />Activate</button>@endperm
            @elseif ($c->status === 'active')@perm('contracts', 'E')<button type="button" class="btn btn-d" wire:click="open('end')">Terminate</button>@endperm @perm('contracts', 'C')<button type="button" class="btn btn-p" wire:click="open('release')"><x-icon name="plus" />Release order</button>@endperm @endif
        @endif
    </x-ph>
    <x-facts :items="[['Period', $c->starts_on->format('j M Y') . ' - ' . $c->ends_on->format('j M Y')], ['Ceiling', $c->ceiling_cents ? Money::format($c->ceiling_cents, $c->currency) : 'No ceiling', '', true], ['Released', Money::format($c->released_cents, $c->currency), $c->ceiling_cents ? '· ' . $pct . '%' : '', true], ['Left', $c->ceiling_cents ? Money::format($c->remainingCents(), $c->currency) : 'Unlimited', '', true], ['Owner', $c->owner->name]]" />
    @if ($c->status === 'terminated')<div style="margin-bottom:16px"><x-note kind="bad"><b>Terminated.</b> {{ $c->end_reason }}</x-note></div>
    @elseif ($c->status === 'active' && $c->ends_on->lte(now()->addDays(30)))<div style="margin-bottom:16px"><x-note kind="warn"><b>Ends {{ $c->ends_on->diffForHumans(['parts' => 1]) }}.</b> Start the renewal or a new request in time.</x-note></div>@endif
    <x-tabs :on="$tab" :tabs="['prices' => ['Price list', $c->items->count()], 'releases' => ['Release orders', $c->releases->count()], 'terms' => 'Terms', 'history' => 'History']" />
    @if ($tab === 'prices')
        <x-card :flush="true"><div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Item</th><th>Unit</th><th class="num">Unit price</th></tr></thead><tbody>@foreach ($c->items as $it)<tr><td class="t1">{{ $it->name }}</td><td>{{ $it->unit }}</td><td class="num mono b">{{ Money::format($it->unit_price_cents, $c->currency) }}</td></tr>@endforeach</tbody></table></div></x-card>
    @elseif ($tab === 'releases')
        @if ($c->releases->isEmpty())<x-card><x-empty icon="box" title="No release orders yet" text="A release order calls off quantities at the contract prices." /></x-card>
        @else
            <div class="stack" style="gap:10px">
                @foreach ($c->releases->sortByDesc('id') as $ro)
                    <details class="qd" wire:key="ro-{{ $ro->id }}" @if ($loop->first) open @endif><summary><span style="flex:1;min-width:160px"><b class="mono">{{ $ro->number }}</b><span class="muted sm" style="display:block">{{ $ro->created_at->format('j M Y') }} · by {{ $ro->creator->name }}@if ($ro->needed_by) · needed by {{ $ro->needed_by->format('j M Y') }}@endif</span></span>
                        <span class="num"><span class="muted xs" style="display:block">Total</span><b class="mono" style="font-size:15px">{{ Money::format($ro->total_cents, $c->currency) }}</b></span><x-badge :status="['issued' => 'info', 'accepted' => 'warn', 'delivered' => 'ok', 'cancelled' => 'bad'][$ro->status]" :label="$ro->statusLabel()" />
                        @perm('contracts', 'E')
                            @if ($ro->status === 'issued' && ! $sourcing)<button type="button" class="btn btn-sm btn-p" wire:click="move({{ $ro->id }}, 'accepted')">Accept</button>@endif
                            @if ($ro->status === 'issued' && $sourcing)<button type="button" class="btn btn-sm btn-d" wire:click="move({{ $ro->id }}, 'cancelled')" wire:confirm="Cancel {{ $ro->number }}? Its value goes back to the contract.">Cancel</button>@endif
                            @if ($ro->status === 'accepted' && $sourcing)<button type="button" class="btn btn-sm btn-p" wire:click="move({{ $ro->id }}, 'delivered')">Confirm delivery</button>@endif
                        @endperm
                        <span class="cv"><x-icon name="down" /></span></summary>
                        <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Unit price</th><th class="num">Total</th></tr></thead><tbody>@foreach ($ro->items as $l)<tr><td class="t1">{{ $l->name }}</td><td class="num mono">{{ Money::qty($l->quantity_milli) }} <span class="faint">{{ $l->unit }}</span></td><td class="num mono">{{ Money::format($l->unit_price_cents, $c->currency) }}</td><td class="num mono b">{{ Money::format($l->total_cents, $c->currency) }}</td></tr>@endforeach</tbody></table></div>
                        @if ($ro->note)<div class="sm muted" style="padding:10px 16px;border-top:1px solid var(--line)">Note: {{ $ro->note }}</div>@endif
                    </details>
                @endforeach
            </div>
        @endif
    @elseif ($tab === 'terms')
        <x-card><dl class="kv"><dt>Supplier</dt><dd>{{ $c->supplier->name }}</dd><dt>Payment terms</dt><dd>{{ $c->payment_terms ?: '-' }}</dd><dt>Currency</dt><dd>{{ $c->currency }}</dd><dt>Terms and notes</dt><dd style="white-space:pre-line">{{ $c->terms ?: '-' }}</dd></dl></x-card>
    @else
        <x-card><x-history :logs="$logs" /></x-card>
    @endif
    @if ($modal === 'release')
        <x-modal title="New release order" :wide="true">
            <p class="muted" style="margin-bottom:12px">Enter the quantities to call off at the contract prices.@if ($c->ceiling_cents) {{ Money::format($c->remainingCents(), $c->currency) }} is left under the ceiling.@endif</p>
            <div class="tbl-wrap"><table class="tbl compact" style="min-width:0"><thead><tr><th>Item</th><th class="num">Unit price</th><th style="width:150px">Quantity</th></tr></thead><tbody>@foreach ($c->items as $it)<tr><td class="t1">{{ $it->name }}</td><td class="num mono">{{ Money::format($it->unit_price_cents, $c->currency) }} <span class="faint">/{{ $it->unit }}</span></td><td><input class="input mono" inputmode="decimal" wire:model="qty.{{ $it->id }}" aria-label="Quantity of {{ $it->name }}" placeholder="0"></td></tr>@endforeach</tbody></table></div>
            <div class="fgrid" style="margin-top:14px"><x-field label="Needed by" for="ro-need" :opt="true"><input id="ro-need" type="date" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="neededBy"></x-field><x-field label="Note to the supplier" for="ro-note" :opt="true"><input id="ro-note" class="input" wire:model="note"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="release" wire:loading.attr="disabled">Send release order</button></x-slot:foot>
        </x-modal>
    @elseif ($modal === 'end')
        <x-modal title="Terminate this contract?"><p style="margin-bottom:12px">No more release orders can be placed on <b class="mono">{{ $c->number }}</b>. This cannot be undone.</p>
            <x-field label="Reason" for="ct-why"><textarea id="ct-why" class="textarea" rows="3" wire:model="reason" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Keep it</button><button type="button" class="btn btn-d solid" wire:click="terminate">Terminate</button></x-slot:foot></x-modal>
    @endif
</div>
