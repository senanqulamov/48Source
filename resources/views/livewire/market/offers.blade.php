@php use App\Support\Money; $quick = ['' => ['All', $counts->sum()], 'active' => ['On sale', $counts['active'] ?? 0], 'paused' => ['Paused', $counts['paused'] ?? 0]]; @endphp
<div>
    <x-ph title="My products" :desc="$store->name . ' · ' . $store->market->name . '. Your prices, stock and smallest order for each product.'" :crumbs="[['My products']]">
        @perm('catalog', 'C')<button type="button" class="btn" wire:click="open('import')"><x-icon name="upload" />Import price list</button><button type="button" class="btn btn-p" wire:click="create"><x-icon name="plus" />Add product</button>@endperm
    </x-ph>
    <x-dt :lw="$this" :rows="$rows" id="of" search="Search product or SKU" label="Search my products" noun="products" :all="$all" :quick="$quick" :export="true">
        <x-slot:empty><x-empty icon="box" title="No products yet" text="Add your first product, or import your whole price list from Excel.">@perm('catalog', 'C')<button type="button" class="btn btn-p" wire:click="create">Add product</button>@endperm</x-empty></x-slot:empty>
        <x-slot:head><th>Product</th><th class="hide-md">Category</th><x-th col="price_cents" :num="true">Price</x-th><th class="num hide-sm">Smallest order</th><x-th col="stock_milli" :num="true">Stock</x-th><x-th col="lead_days" cls="hide-md" :num="true">Ready in</x-th><x-th col="status">Status</x-th><th style="width:150px"><span class="sr">Actions</span></th></x-slot:head>
        @foreach ($rows as $x)
            <tr wire:key="of-{{ $x->id }}"><td class="tmain"><div class="t1">{{ $x->product->name }}</div><div class="t2 mono">{{ $x->product->sku }}</div></td><td class="hide-md">{{ $x->product->category?->name ?? '-' }}</td>
                <td class="num mono b">{{ Money::format($x->price_cents, $x->currency) }}<span class="faint"> /{{ $x->product->unit }}</span></td><td class="num mono hide-sm">{{ Money::qty($x->moq_milli) }}</td>
                <td class="num mono" @if ($x->stock_milli <= 0) style="color:var(--bad)" @endif>{{ Money::qty($x->stock_milli) }}</td><td class="num mono hide-md">{{ $x->lead_days }} d</td>
                <td><x-badge :status="$x->status === 'active' ? ($x->stock_milli > 0 ? 'ok' : 'warn') : 'draft'" :label="$x->status === 'active' ? ($x->stock_milli > 0 ? 'On sale' : 'Out of stock') : 'Paused'" /></td>
                <td class="num">@perm('catalog', 'E')<div class="row" style="justify-content:flex-end"><button type="button" class="btn btn-sm btn-g" wire:click="toggle({{ $x->id }})">{{ $x->status === 'active' ? 'Pause' : 'Resume' }}</button><button type="button" class="icon-btn" wire:click="edit({{ $x->id }})" aria-label="Edit {{ $x->product->name }}"><x-icon name="edit" :size="15" /></button></div>@endperm</td></tr>
        @endforeach
    </x-dt>
    @if ($modal === 'form')
        <x-modal :title="$editId ? 'Edit product' : 'Add product'" :wide="true"><div class="fgrid">
            <x-field label="SKU" for="of-sku" help="If the SKU already exists in the catalogue, your offer is added to that product."><input id="of-sku" class="input mono" wire:model="o.sku" @disabled((bool) $editId)></x-field>
            <x-field label="Name" for="of-name"><input id="of-name" class="input" wire:model="o.name" @disabled((bool) $editId)></x-field>
            <x-field label="Unit" for="of-unit"><input id="of-unit" class="input" wire:model="o.unit" @disabled((bool) $editId)></x-field>
            <x-field label="Category" for="of-cat" :opt="true"><select id="of-cat" class="select" wire:model="o.category" @disabled((bool) $editId)><option value="">No category</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
            <x-field label="Price per unit" for="of-price"><input id="of-price" class="input mono" inputmode="decimal" wire:model="o.price" placeholder="0.00"></x-field>
            <x-field label="Stock" for="of-stock"><input id="of-stock" class="input mono" inputmode="decimal" wire:model="o.stock" placeholder="0"></x-field>
            <x-field label="Smallest order" for="of-moq"><input id="of-moq" class="input mono" inputmode="decimal" wire:model="o.moq"></x-field>
            <x-field label="Ready in, days" for="of-lead"><input id="of-lead" class="input mono" inputmode="numeric" wire:model="o.lead_days"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="save" wire:loading.attr="disabled">Save</button></x-slot:foot></x-modal>
    @elseif ($modal === 'import')
        <x-modal title="Import a price list" :wide="true">
            <p style="margin-bottom:12px">Upload an Excel (.xlsx) or CSV file. The first row holds the column names: <span class="mono">sku, name, unit, category, price, moq, stock, lead_days</span>. Existing SKUs are updated, new ones are added.</p>
            <div class="row" style="margin-bottom:12px"><input type="file" class="input" style="padding:8px" wire:model="file" accept=".xlsx,.csv" aria-label="Price list file"><button type="button" class="btn btn-g" wire:click="template"><x-icon name="download" />Template</button></div>
            @error('file')<span class="errt">{{ $message }}</span>@enderror
            <div wire:loading wire:target="file" class="muted sm">Uploading the file...</div>
            @if ($report)<x-note :kind="$report['errors'] ? 'warn' : 'ok'"><b>{{ $report['saved'] }} offer(s) saved.</b>@if ($report['errors']) {{ count($report['errors']) }} row(s) were skipped:<ul style="margin:6px 0 0 18px">@foreach ($report['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</x-note>@endif
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Close</button><button type="button" class="btn btn-p" wire:click="import" wire:loading.attr="disabled" wire:target="file,import">Import</button></x-slot:foot></x-modal>
    @endif
</div>
