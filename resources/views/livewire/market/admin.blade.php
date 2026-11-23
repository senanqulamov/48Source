<div>
    <x-ph title="Markets & stores" desc="The markets, the stores that stand in them, and which seller and worker accounts belong to each store." :crumbs="[['Markets & stores']]">
        <button type="button" class="btn" wire:click="market"><x-icon name="plus" />Add market</button><button type="button" class="btn btn-p" wire:click="store"><x-icon name="plus" />Add store</button>
    </x-ph>
    <div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,2fr);align-items:start">
        <x-card title="Markets" :sub="$markets->count() . ' in total'" :flush="true">
            @if ($markets->isEmpty())<x-empty icon="pin" title="No markets yet" text="Add the first market, then its stores." />
            @else<ul class="list">@foreach ($markets as $m)<li><div class="grow"><div class="t1">{{ $m->name }}</div><div class="muted sm">{{ $m->city ?? 'No city' }} · {{ $m->stores_count }} store{{ $m->stores_count === 1 ? '' : 's' }}</div></div><x-badge :status="$m->status === 'active' ? 'ok' : 'bad'" :label="ucfirst($m->status)" /><button type="button" class="icon-btn" wire:click="market({{ $m->id }})" aria-label="Edit {{ $m->name }}"><x-icon name="edit" :size="15" /></button></li>@endforeach</ul>@endif
        </x-card>
        <x-card title="Stores" :sub="$stores->count() . ' in total'" :flush="true">
            @if ($stores->isEmpty())<x-empty icon="store" title="No stores yet" text="A store needs a market and at least one seller account." />
            @else<div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Store</th><th>Market</th><th>People</th><th class="num">Products</th><th>Status</th><th style="width:44px"></th></tr></thead><tbody>
                @foreach ($stores as $s)<tr><td class="tmain"><div class="t1">{{ $s->name }}</div><div class="t2">{{ $s->stall ? 'Stall ' . $s->stall : 'No stall number' }}</div></td><td>{{ $s->market->name }}</td><td>@forelse ($s->users as $usr)<div class="sm">{{ $usr->name }} <span class="faint">{{ $usr->roleLabel() }}</span></div>@empty<span class="faint">Nobody yet</span>@endforelse</td><td class="num mono">{{ $s->offers_count }}</td>
                    <td><x-badge :status="$s->status === 'active' ? 'ok' : 'warn'" :label="ucfirst($s->status)" /></td><td><button type="button" class="icon-btn" wire:click="store({{ $s->id }})" aria-label="Edit {{ $s->name }}"><x-icon name="edit" :size="15" /></button></td></tr>@endforeach
            </tbody></table></div>@endif
        </x-card>
    </div>
    <div style="margin-top:16px"><x-card title="Products" :sub="$products->count() . ' in the catalogue, listed by the stores'" :flush="true">
        @if ($products->isEmpty())<x-empty icon="box" title="No products yet" text="Stores add products when they build their price list." />
        @else<div class="tbl-wrap"><table class="tbl"><thead><tr><th class="hide-sm">SKU</th><th>Product</th><th class="hide-md">Category</th><th class="num">Sellers</th><th class="num">From</th><th>Status</th><th style="width:110px"></th></tr></thead><tbody>
            @foreach ($products as $pr)<tr wire:key="pr-{{ $pr->id }}"><td class="mono hide-sm">{{ $pr->sku }}</td><td class="t1">{{ $pr->name }} <span class="faint">/ {{ $pr->unit }}</span></td><td class="hide-md muted">{{ $pr->category?->name ?? '-' }}</td><td class="num mono">{{ $pr->offers_count }}</td><td class="num mono">{{ $pr->offers_min_price_cents !== null ? \App\Support\Money::format((int) $pr->offers_min_price_cents, 'USD') : '-' }}</td>
                <td><x-badge :status="$pr->status === 'active' ? 'ok' : 'draft'" :label="$pr->status === 'active' ? 'On sale' : 'Retired'" /></td><td class="num"><button type="button" class="btn btn-sm btn-g" wire:click="retire({{ $pr->id }})">{{ $pr->status === 'active' ? 'Retire' : 'Restore' }}</button></td></tr>@endforeach
        </tbody></table></div>@endif
    </x-card></div>
    @if ($modal === 'market')
        <x-modal :title="$editId ? 'Edit market' : 'Add market'"><div class="stack" style="gap:14px"><x-field label="Name" for="mk-n"><input id="mk-n" class="input" wire:model="m.name" autofocus></x-field><x-field label="City" for="mk-c" :opt="true"><input id="mk-c" class="input" wire:model="m.city"></x-field><x-field label="Address" for="mk-a" :opt="true"><input id="mk-a" class="input" wire:model="m.address"></x-field>
            <x-field label="Status" for="mk-s"><select id="mk-s" class="select" wire:model="m.status"><option value="active">Active</option><option value="closed">Closed</option></select></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="saveMarket">Save market</button></x-slot:foot></x-modal>
    @elseif ($modal === 'store')
        <x-modal :title="$editId ? 'Edit store' : 'Add store'" :wide="true"><div class="fgrid">
            <x-field label="Name" for="st-n"><input id="st-n" class="input" wire:model="s.name" autofocus></x-field><x-field label="Market" for="st-m"><select id="st-m" class="select" wire:model="s.market_id"><option value="">Choose a market</option>@foreach ($markets as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></x-field>
            <x-field label="Stall" for="st-s" :opt="true"><input id="st-s" class="input" wire:model="s.stall"></x-field><x-field label="Phone" for="st-p" :opt="true"><input id="st-p" class="input" wire:model="s.phone"></x-field>
            <x-field label="Status" for="st-st" help="A paused store is hidden from the marketplace."><select id="st-st" class="select" wire:model="s.status"><option value="active">Active</option><option value="paused">Paused</option></select></x-field></div>
            <h3 class="lbl" style="margin:16px 0 8px">Seller and worker accounts of this store</h3>
            @if ($people->isEmpty())<p class="faint">No seller or market worker accounts exist yet. Invite them under Users & roles first.</p>
            @else<ul class="list" style="border:1px solid var(--line);border-radius:12px;max-height:220px;overflow:auto">@foreach ($people as $usr)<li><label class="row grow" style="gap:12px;cursor:pointer"><input type="checkbox" class="chk" value="{{ $usr->id }}" wire:model="s.members"><div class="grow"><div class="t1">{{ $usr->name }}</div><div class="muted sm">{{ $usr->roleLabel() }}{{ $usr->store && $usr->store_id !== $editId ? ' · now at ' . $usr->store->name : '' }}</div></div></label></li>@endforeach</ul>@endif
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="saveStore">Save store</button></x-slot:foot></x-modal>
    @endif
</div>
