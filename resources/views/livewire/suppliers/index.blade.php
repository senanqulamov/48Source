@php use App\Models\Supplier; $moves = ['active' => ['Approve', 'btn-p'], 'inactive' => ['Deactivate', ''], 'blocked' => ['Block', 'btn-d']]; @endphp
<div>
    <x-ph title="Suppliers" desc="Every supplier, its status and its track record. Only active suppliers can be invited or send quotations." :crumbs="[['Suppliers']]">
        @perm('suppliers', 'C')<button type="button" class="btn btn-p" wire:click="create"><x-icon name="plus" />Add supplier</button>@endperm
    </x-ph>
    @php $quick = ['' => ['All', $counts->sum()]]; foreach (Supplier::STATUSES as $k => $l) { $quick[$k] = [$l, $counts[$k] ?? 0]; } @endphp
    <x-dt :lw="$this" :rows="$rows" id="sup" search="Search name, city, contact or email" label="Search suppliers" noun="suppliers" :all="$counts->sum()" :quick="$quick" :facets="['cat' => ['Category', $catNames]]"
          :cols="['cat' => 'Category', 'part' => 'Participation', 'contact' => 'Contact']" :export="true">
        <x-slot:empty><x-empty icon="building" title="No suppliers yet" text="Add the first supplier, or let suppliers register themselves." /></x-slot:empty>
        <x-slot:head>
            <x-th col="name" :sort="$sort ?: 'name'" :dir="$sort === '' ? 'asc' : $dir">Supplier</x-th><x-th col="status">Status</x-th><th class="hide-sm" data-c="cat">Category</th><th class="hide-md" data-c="part">Participation</th>
            <x-th col="rating_tenths">Performance</x-th><th class="hide-lg" data-c="contact">Contact</th>
        </x-slot:head>
        @foreach ($rows as $s)
            @php $resp = $s->invitations_count ? intdiv($s->quotes_count * 100, max(1, $s->invitations_count)) : 0; @endphp
            <tr wire:key="s-{{ $s->id }}" class="click" wire:click="show({{ $s->id }})" tabindex="0" wire:keydown.enter="show({{ $s->id }})">
                <td class="tmain"><div class="row"><span class="avatar av{{ $s->id % 5 }}" aria-hidden="true">{{ \Illuminate\Support\Str::of($s->name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</span><div><div class="t1">{{ $s->name }}</div><div class="t2">{{ $s->city ?? 'No city' }} · since {{ $s->created_at->format('Y') }}</div></div></div></td>
                <td><x-badge :status="$s->status" :label="$s->statusLabel()" /></td>
                <td class="hide-sm" data-c="cat">@if ($s->category)<span class="tag">{{ $s->category->name }}</span>@else<span class="faint">None</span>@endif</td>
                <td class="hide-md" data-c="part"><div class="mono">{{ $s->invitations_count }} invited · {{ $s->purchase_orders_count }} awarded</div><div class="t2">{{ min(100, $resp) }}% response rate</div></td>
                <td style="min-width:130px"><div class="row"><x-stars :tenths="$s->rating_tenths" /></div><div class="t2" style="margin-top:3px">{{ $s->quotes_count }} quotation{{ $s->quotes_count === 1 ? '' : 's' }}</div></td>
                <td class="hide-lg" data-c="contact"><div>{{ $s->contact_name ?? '-' }}</div><div class="t2">{{ $s->email ?? 'No email' }}</div></td>
            </tr>
        @endforeach
    </x-dt>

    @if ($modal === 'drawer' && $current)
        <x-drawer :title="$current->name">
            <div class="seg dw-tabs" role="tablist" style="display:flex;margin-bottom:16px">
                <button type="button" style="flex:1;justify-content:center" @class(['on' => $pane === 'details']) wire:click="$set('pane', 'details')">Details</button>
                <button type="button" style="flex:1;justify-content:center" @class(['on' => $pane === 'history']) wire:click="$set('pane', 'history')">History</button>
            </div>
            @if ($pane === 'details')
                <div class="row" style="margin-bottom:12px"><x-badge :status="$current->status" :label="$current->statusLabel()" /><span class="tag">{{ $current->category?->name ?? 'No category' }}</span><span class="mono sm">{{ $current->rating() }} ★</span></div>
                @if ($current->status_reason)<div style="margin-bottom:12px"><x-note :kind="$current->status === 'blocked' ? 'bad' : 'info'">{{ $current->status_reason }}</x-note></div>@endif
                <dl class="kv" style="grid-template-columns:130px 1fr"><dt>Contact</dt><dd>{{ $current->contact_name ?? '-' }}</dd><dt>Email</dt><dd style="overflow-wrap:anywhere">{{ $current->email ?? '-' }}</dd><dt>Phone</dt><dd>{{ $current->phone ?? '-' }}</dd><dt>City</dt><dd>{{ $current->city ?? '-' }}</dd><dt>Invitations</dt><dd class="mono">{{ $current->invitations_count }}</dd><dt>Orders won</dt><dd class="mono">{{ $current->purchase_orders_count }}</dd><dt>Approved</dt><dd>{{ $current->approved_at?->format('j M Y') ?? '-' }}</dd><dt>Added</dt><dd>{{ $current->created_at->format('j M Y') }}</dd></dl>
                <h3 class="lbl" style="margin:16px 0 8px">User accounts</h3>
                @if ($current->users->isEmpty())<p class="faint">No one from this company can sign in yet. Invite a user under Users & roles.</p>
                @else<ul class="list">@foreach ($current->users as $usr)<li><div class="grow"><div class="t1">{{ $usr->name }}</div><div class="muted sm">{{ $usr->email }} · {{ $usr->roleLabel() }}</div></div><x-badge :status="$usr->status" /></li>@endforeach</ul>@endif
            @else
                <x-history :logs="$logs" />
            @endif
            <x-slot:foot>
                <a class="btn btn-p" href="{{ route('suppliers.show', $current->id) }}" wire:navigate><x-icon name="shield" />Open supplier 360</a>
                @perm('suppliers', 'E')<button type="button" class="btn" wire:click="edit({{ $current->id }})"><x-icon name="edit" />Edit</button>@endperm
                <span class="sp"></span>
                @perm('suppliers', 'A')@foreach (Supplier::TRANSITIONS[$current->status] ?? [] as $to)<button type="button" class="btn {{ $moves[$to][1] }}" wire:click="askStatus({{ $current->id }}, '{{ $to }}')">{{ $current->status !== 'pending' && $to === 'active' ? 'Reactivate' : $moves[$to][0] }}</button>@endforeach @endperm
            </x-slot:foot>
        </x-drawer>
    @elseif ($modal === 'form')
        <x-modal :title="$f['id'] ? 'Edit supplier' : 'Add supplier'">
            <form class="fgrid" wire:submit="save" id="supForm" novalidate>
                <x-field label="Company name" for="sn" name="f.name" :full="true"><input id="sn" class="input" wire:model="f.name" autofocus></x-field>
                <x-field label="Category" for="sc" name="f.category_id" :opt="true"><select id="sc" class="select" wire:model="f.category_id"><option value="">Choose…</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
                <x-field label="City" for="sci" name="f.city" :opt="true"><input id="sci" class="input" wire:model="f.city"></x-field>
                <x-field label="Contact person" for="scn" name="f.contact_name" :opt="true"><input id="scn" class="input" wire:model="f.contact_name"></x-field>
                <x-field label="Email" for="se" name="f.email" :opt="true"><input id="se" class="input" type="email" wire:model="f.email"></x-field>
                <x-field label="Phone" for="sp" name="f.phone" :opt="true"><input id="sp" class="input" wire:model="f.phone"></x-field>
            </form>
            @unless ($f['id'])<p class="help" style="margin-top:10px">{{ auth()->user()->allowed('suppliers', 'A') ? 'The supplier is active at once and can be invited.' : 'The supplier waits for an administrator to approve it.' }}</p>@endunless
            <x-slot:foot><button type="button" class="btn" wire:click="close">Cancel</button><button type="submit" form="supForm" class="btn btn-p">Save supplier</button></x-slot:foot>
        </x-modal>
    @elseif ($modal === 'status' && $current)
        <x-modal :title="($current->status !== 'pending' && $to === 'active' ? 'Reactivate' : $moves[$to][0]) . ' ' . $current->name . '?'">
            <p style="margin-bottom:12px">{{ ['active' => 'The supplier can be invited to requests and send quotations.', 'inactive' => 'The supplier keeps its history but cannot be invited or quote until it is reactivated.', 'blocked' => 'The supplier cannot be invited, quote or be awarded. Its users are told.'][$to] }}</p>
            <x-field label="Reason" for="sr" :opt="$to === 'active'" help="Recorded in the supplier's history and shared with the supplier."><textarea id="sr" class="textarea" rows="3" wire:model="reason" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" wire:click="close">Cancel</button><button type="button" class="btn {{ $to === 'blocked' ? 'btn-d solid' : 'btn-p' }}" wire:click="changeStatus">Confirm</button></x-slot:foot>
        </x-modal>
    @endif
</div>
