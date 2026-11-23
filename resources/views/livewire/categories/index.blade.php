<div>
    <x-ph title="Categories" desc="Two levels: groups and the categories inside them. Categories decide which suppliers are suggested for a request." :crumbs="[['Categories']]">
        @perm('catalog', 'C')<button type="button" class="btn btn-p" wire:click="create"><x-icon name="plus" />Add category</button>@endperm
    </x-ph>
    <x-card :flush="true">
        @if ($groups->isEmpty())
            <x-empty icon="flag" title="No categories yet" text="Add a group first, then the categories inside it." />
        @else
            <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Category</th><th class="num">Suppliers</th><th class="num">Requests</th><th style="width:60px"></th></tr></thead><tbody>
                @foreach ($groups as $g)
                    <tr wire:key="g{{ $g->id }}" style="background:var(--surface-2)"><td class="b"><x-icon name="flag" :size="14" /> {{ $g->name }}</td><td class="num mono b">{{ $g->children->sum('suppliers_count') }}</td><td class="num mono b">{{ $g->children->sum('rfqs_count') }}</td>
                        <td class="num">@perm('catalog', 'E')<button type="button" class="icon-btn" wire:click="edit({{ $g->id }})" aria-label="Edit {{ $g->name }}"><x-icon name="edit" :size="15" /></button>@endperm</td></tr>
                    @foreach ($g->children as $c)
                        <tr wire:key="c{{ $c->id }}"><td class="t1" style="padding-left:40px">{{ $c->name }}</td><td class="num mono">{{ $c->suppliers_count }}</td><td class="num mono">{{ $c->rfqs_count }}</td>
                            <td class="num">@perm('catalog', 'E')<button type="button" class="icon-btn" wire:click="edit({{ $c->id }})" aria-label="Edit {{ $c->name }}"><x-icon name="edit" :size="15" /></button>@endperm</td></tr>
                    @endforeach
                @endforeach
            </tbody></table></div>
        @endif
    </x-card>
    @if ($modal === 'form')
        <x-modal :title="$f['id'] ? 'Edit category' : 'Add category'">
            <form class="stack" wire:submit="save" id="catForm" novalidate>
                <x-field label="Name" for="cn" name="f.name"><input id="cn" class="input" wire:model="f.name" autofocus></x-field>
                <x-field label="Group" for="cp" name="f.parent_id" help="Leave empty to create a new group."><select id="cp" class="select" wire:model="f.parent_id"><option value="">None - this is a group</option>@foreach ($parents as $id => $n)@continue($id === $f['id'])<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
            </form>
            <x-slot:foot><button type="button" class="btn" wire:click="close">Cancel</button><button type="submit" form="catForm" class="btn btn-p">Save category</button></x-slot:foot>
        </x-modal>
    @endif
</div>
