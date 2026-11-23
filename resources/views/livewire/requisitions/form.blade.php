@php use App\Support\Money; @endphp
<div>
    <x-ph :title="$number ? 'Edit ' . $number : 'New requisition'" desc="Say what is needed and why. The estimated total decides who has to approve it." :crumbs="[['Requisitions', route('requisitions.index')], [$number ?? 'New requisition']]">
        <a class="btn btn-g" href="{{ $number ? route('requisitions.show', $number) : route('requisitions.index') }}" wire:navigate>Discard</a>
    </x-ph>
    <div class="grid g-2-1">
        <div class="stack">
            <x-card title="What is needed">
                <div class="fgrid">
                    <x-field label="Title" for="pr-title" :full="true"><input id="pr-title" class="input" wire:model="f.title" placeholder="e.g. Laptops for the new site office" autofocus></x-field>
                    <x-field label="Category" for="pr-cat" :opt="true"><select id="pr-cat" class="select" wire:model="f.category_id"><option value="">No category</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
                    <x-field label="Needed by" for="pr-need" :opt="true"><input id="pr-need" type="date" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="f.needed_by"></x-field>
                    <x-field label="Why it is needed" for="pr-why" :full="true" :opt="true" help="Approvers read this first."><textarea id="pr-why" class="textarea" rows="3" wire:model="f.justification"></textarea></x-field>
                </div>
            </x-card>
            <x-card title="Items" sub="Estimated prices are optional but decide the approval chain" :flush="true">
                <x-slot:act><button type="button" class="btn btn-sm" wire:click="addItem"><x-icon name="plus" :size="14" />Add item</button></x-slot:act>
                <div class="tbl-wrap"><table class="tbl compact" style="min-width:640px"><thead><tr><th style="width:36px">#</th><th>Item</th><th style="width:120px">Quantity</th><th style="width:100px">Unit</th><th style="width:150px">Est. unit price</th><th style="width:44px"></th></tr></thead><tbody>
                    @foreach ($items as $i => $row)
                        <tr wire:key="pri-{{ $i }}"><td class="mono muted">{{ $i + 1 }}</td>
                            <td><input class="input" wire:model.blur="items.{{ $i }}.name" aria-label="Item {{ $i + 1 }} name" placeholder="What is needed"></td>
                            <td><input class="input mono" inputmode="decimal" wire:model.blur="items.{{ $i }}.quantity" aria-label="Item {{ $i + 1 }} quantity" placeholder="0"></td>
                            <td><input class="input" wire:model.blur="items.{{ $i }}.unit" aria-label="Item {{ $i + 1 }} unit"></td>
                            <td><input class="input mono" inputmode="decimal" wire:model.blur="items.{{ $i }}.est_price" aria-label="Item {{ $i + 1 }} estimated price" placeholder="optional"></td>
                            <td><button type="button" class="icon-btn" wire:click="removeItem({{ $i }})" aria-label="Remove item {{ $i + 1 }}"><x-icon name="trash" :size="15" /></button></td></tr>
                    @endforeach
                </tbody></table></div>
            </x-card>
        </div>
        <div class="stack">
            <x-card title="Approval chain" sub="From the estimated total">
                <div class="row" style="align-items:baseline;margin-bottom:12px"><span class="strip-v" style="font-size:26px" data-done="1">{{ Money::format($total, $f['currency']) }}</span><span class="muted sm">estimated</span></div>
                <ol class="steps-v">@foreach ($chain as $i => $level)<li><b>{{ $level['label'] }}</b><div class="muted sm">{{ \App\Support\Perm::ROLES[$level['role']] }}{{ $level['min_cents'] ? ' · from ' . Money::format($level['min_cents'], $f['currency'], false) : '' }}</div></li>@endforeach</ol>
                <p class="help" style="margin-top:10px">Nobody approves their own requisition.</p>
            </x-card>
        </div>
    </div>
    <div class="row" style="margin-top:18px"><span class="sp"></span><button type="button" class="btn" wire:click="save(false)" wire:loading.attr="disabled">Save draft</button><button type="button" class="btn btn-p" wire:click="save(true)" wire:loading.attr="disabled"><x-icon name="send" />Submit for approval</button></div>
</div>
