<div>
    <x-ph :title="$number ? 'Edit ' . $number : 'New contract'" desc="Agree the price list, the period and an optional value ceiling. The contract starts as a draft." :crumbs="[['Contracts', route('contracts.index')], [$number ?? 'New contract']]">
        <a class="btn btn-g" href="{{ $number ? route('contracts.show', $number) : route('contracts.index') }}" wire:navigate>Discard</a>
    </x-ph>
    <div class="stack">
        <x-card title="Agreement">
            <div class="fgrid">
                <x-field label="Title" for="ct-title" :full="true"><input id="ct-title" class="input" wire:model="f.title" placeholder="e.g. Reinforcement steel framework 2027" autofocus></x-field>
                <x-field label="Supplier" for="ct-sup" help="Only active suppliers are listed."><select id="ct-sup" class="select" wire:model="f.supplier_id"><option value="">Choose a supplier</option>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
                <x-field label="Value ceiling" for="ct-ceil" :opt="true" help="Release orders cannot go over this amount."><input id="ct-ceil" class="input mono" inputmode="decimal" wire:model="f.ceiling" placeholder="No ceiling"></x-field>
                <x-field label="Starts on" for="ct-from"><input id="ct-from" type="date" class="input" wire:model="f.starts_on"></x-field>
                <x-field label="Ends on" for="ct-to"><input id="ct-to" type="date" class="input" wire:model="f.ends_on"></x-field>
                <x-field label="Currency" for="ct-cur"><select id="ct-cur" class="select" wire:model="f.currency">@foreach (\App\Models\Currency::codes() as $c)<option>{{ $c }}</option>@endforeach</select></x-field>
                <x-field label="Payment terms" for="ct-pay" :opt="true"><input id="ct-pay" class="input" wire:model="f.payment_terms"></x-field>
                <x-field label="Terms and notes" for="ct-terms" :full="true" :opt="true"><textarea id="ct-terms" class="textarea" rows="3" wire:model="f.terms"></textarea></x-field>
            </div>
        </x-card>
        <x-card title="Price list" sub="Fixed unit prices for the whole period" :flush="true">
            <x-slot:act><button type="button" class="btn btn-sm" wire:click="addItem"><x-icon name="plus" :size="14" />Add item</button></x-slot:act>
            <div class="tbl-wrap"><table class="tbl compact" style="min-width:560px"><thead><tr><th style="width:36px">#</th><th>Item</th><th style="width:120px">Unit</th><th style="width:170px">Unit price</th><th style="width:44px"></th></tr></thead><tbody>
                @foreach ($items as $i => $row)
                    <tr wire:key="cti-{{ $i }}"><td class="mono muted">{{ $i + 1 }}</td><td><input class="input" wire:model="items.{{ $i }}.name" aria-label="Item {{ $i + 1 }} name"></td><td><input class="input" wire:model="items.{{ $i }}.unit" aria-label="Item {{ $i + 1 }} unit"></td>
                        <td><input class="input mono" inputmode="decimal" wire:model="items.{{ $i }}.price" aria-label="Item {{ $i + 1 }} unit price" placeholder="0.00"></td><td><button type="button" class="icon-btn" wire:click="removeItem({{ $i }})" aria-label="Remove item {{ $i + 1 }}"><x-icon name="trash" :size="15" /></button></td></tr>
                @endforeach
            </tbody></table></div>
        </x-card>
    </div>
    <div class="row" style="margin-top:18px"><span class="sp"></span><button type="button" class="btn btn-p" wire:click="save" wire:loading.attr="disabled">Save draft</button></div>
</div>
