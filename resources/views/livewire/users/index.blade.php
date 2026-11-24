@php use App\Support\Perm; $me = auth()->user(); @endphp
<div>
    <x-ph title="Users & roles" desc="Who can sign in, which role they hold and what each role is allowed to do." :crumbs="[['Users & roles']]">
        @perm('users', 'C')<button type="button" class="btn btn-p" wire:click="create"><x-icon name="plus" />Invite user</button>@endperm
    </x-ph>
    <div class="tabs" role="tablist">
        <button type="button" role="tab" @class(['tab', 'on' => $tab === 'users']) wire:click="$set('tab', 'users')">Users<span class="n">{{ $roleCounts->sum() }}</span></button>
        <button type="button" role="tab" @class(['tab', 'on' => $tab === 'roles']) wire:click="$set('tab', 'roles')">Roles & permissions<span class="n">{{ count(Perm::ROLES) }}</span></button>
    </div>

    @if ($tab === 'users')
        @php $quick = ['' => ['All', $roleCounts->sum()]]; foreach (Perm::ROLES as $k => $l) { if ($roleCounts[$k] ?? 0) { $quick[$k] = [$l, $roleCounts[$k]]; } } @endphp
        <x-dt :lw="$this" :rows="$rows" id="usr" :keys="$rows->pluck('id')" search="Search name or email" label="Search users" noun="users" :all="$roleCounts->sum()" :quick="$quick" quick-label="Role"
              :facets="['st' => ['Status', ['active' => 'Active', 'invited' => 'Invited', 'disabled' => 'Disabled']]]" :cols="['org' => 'Organisation', 'last_login_at' => 'Last active']" :export="true">
            <x-slot:head>
                <x-th col="name" :sort="$sort ?: 'name'" :dir="$sort === '' ? 'asc' : $dir">User</x-th><x-th col="role">Role</x-th><th class="hide-md" data-c="org">Organisation</th><x-th col="status">Status</x-th><x-th col="last_login_at" cls="hide-sm">Last active</x-th>
            </x-slot:head>
            @foreach ($rows as $usr)
                <tr wire:key="u-{{ $usr->id }}" class="click {{ in_array((string) $usr->id, array_map('strval', $selected), true) ? 'sel' : '' }}" wire:click="show({{ $usr->id }})" tabindex="0" wire:keydown.enter="show({{ $usr->id }})">
                    <td class="hide-sm" x-on:click.stop><input type="checkbox" class="chk" value="{{ $usr->id }}" wire:model.live="selected" aria-label="Select row"></td>
                    <td class="tmain"><div class="row"><span class="avatar av{{ $usr->id % 5 }}" aria-hidden="true">{{ $usr->initials() }}</span><div style="min-width:0"><div class="t1">{{ $usr->name }}@if ($usr->id === $me->id) <span class="tag">You</span>@endif</div><div class="t2">{{ $usr->email }}</div></div></div></td>
                    <td><span class="tag">{{ $usr->roleLabel() }}</span></td><td class="hide-md" data-c="org">{{ $usr->supplier?->name ?? ($usr->department ?: config('app.org', 'Buyer organisation')) }}</td>
                    <td><x-badge :status="$usr->status" /></td><td class="hide-sm muted" data-c="last_login_at">{{ $usr->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                </tr>
            @endforeach
            <x-slot:bulk>
                @perm('users', 'E')<button type="button" class="bb-btn" wire:click="disableSelected" wire:confirm="Deactivate the selected accounts? They will not be able to sign in."><x-icon name="lock" :size="15" />Disable</button>
                <button type="button" class="bb-btn" wire:click="resendSelected"><x-icon name="send" :size="15" />Resend invitation</button>@endperm
                <button type="button" class="bb-btn" wire:click="exportSelected"><x-icon name="download" :size="15" />Export</button>
            </x-slot:bulk>
        </x-dt>
    @else
        <div class="grid" style="grid-template-columns:minmax(220px,280px) minmax(0,1fr);gap:16px">
            <x-card :flush="true"><ul class="list">@foreach (Perm::ROLES as $k => $label)
                <li @class(['on' => $role === $k]) style="{{ $role === $k ? 'background:var(--surface-2)' : '' }}"><button type="button" wire:click="$set('role', '{{ $k }}')" style="all:unset;cursor:pointer;display:flex;flex:1;align-items:center;gap:10px"><div class="grow"><div class="t1">{{ $label }}</div><div class="muted sm">{{ $roleCounts[$k] ?? 0 }} user(s)</div></div><x-icon name="right" :size="14" /></button></li>
            @endforeach</ul></x-card>
            <x-card :title="Perm::ROLES[$role]" sub="What this role may do in each area" :flush="true">
                <div class="tbl-wrap"><table class="tbl compact"><thead><tr><th>Area</th>@foreach (Perm::ABILITIES as $l)<th style="text-align:center;width:90px">{{ $l }}</th>@endforeach</tr></thead><tbody>
                    @foreach (Perm::MODULES as $m => $label)
                        <tr wire:key="{{ $role }}-{{ $m }}"><td class="t1">{{ $label }}</td>
                            @foreach (Perm::ABILITIES as $a => $al)
                                <td style="text-align:center"><input type="checkbox" class="chk perm-x" @checked(str_contains($matrix[$role][$m], $a)) wire:click="togglePerm('{{ $m }}', '{{ $a }}')" @disabled(! $me->allowed('users', 'A') || ($role === 'admin' && $m === 'users')) aria-label="{{ $al }} {{ $label }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody></table></div>
            </x-card>
        </div>
        <div style="margin-top:16px"><x-note kind="info"><b>These permissions are enforced everywhere.</b> View opens the pages and shows the menu item. Create, Edit and Approve decide which buttons a role sees and which actions the system accepts. A change takes effect at once for every user with that role.</x-note></div>
    @endif

    @if ($modal === 'drawer' && $current)
        <x-drawer :title="$current->name">
            <div class="seg" role="tablist" style="display:flex;margin-bottom:16px">
                <button type="button" style="flex:1;justify-content:center" @class(['on' => $pane === 'details']) wire:click="$set('pane', 'details')">Details</button>
                <button type="button" style="flex:1;justify-content:center" @class(['on' => $pane === 'history']) wire:click="$set('pane', 'history')">History</button>
            </div>
            @if ($pane === 'details')
                <div class="row" style="margin-bottom:12px"><x-badge :status="$current->status" /><span class="tag">{{ $current->roleLabel() }}</span></div>
                <dl class="kv" style="grid-template-columns:130px 1fr"><dt>Email</dt><dd style="overflow-wrap:anywhere">{{ $current->email }}</dd><dt>Organisation</dt><dd>{{ $current->supplier?->name ?? 'Buyer organisation' }}</dd><dt>Department</dt><dd>{{ $current->department ?? '-' }}</dd><dt>Email confirmed</dt><dd>{{ $current->email_verified_at?->format('j M Y') ?? 'Not yet' }}</dd><dt>Last sign-in</dt><dd>{{ $current->last_login_at?->format('j M Y, H:i') ?? 'Never' }}</dd><dt>Created</dt><dd>{{ $current->created_at->format('j M Y') }}</dd></dl>
                @if ($current->status === 'invited')<div style="margin-top:12px"><x-note kind="info">This person has not set a password yet.</x-note></div>@endif
            @else
                <x-history :logs="$logs" />
            @endif
            <x-slot:foot>
                @perm('users', 'E')<button type="button" class="btn" wire:click="edit({{ $current->id }})"><x-icon name="edit" />Edit</button>
                @if ($current->status !== 'disabled')<button type="button" class="btn" wire:click="resend({{ $current->id }})">Send set-password link</button>@endif
                <span class="sp"></span>
                @if ($current->id !== $me->id)<button type="button" class="btn {{ $current->status === 'disabled' ? 'btn-p' : 'btn-d' }}" wire:click="toggle({{ $current->id }})" wire:confirm="{{ $current->status === 'disabled' ? 'Reactivate' : 'Deactivate' }} {{ $current->name }}?">{{ $current->status === 'disabled' ? 'Reactivate' : 'Deactivate' }}</button>@endif @endperm
            </x-slot:foot>
        </x-drawer>
    @elseif ($modal === 'form')
        <x-modal :title="$f['id'] ? 'Edit user' : 'Invite user'">
            <form class="fgrid" wire:submit="save" id="userForm" novalidate>
                <x-field label="Full name" for="un" name="f.name"><input id="un" class="input" wire:model="f.name" autofocus></x-field>
                <x-field label="Work email" for="ue" name="f.email"><input id="ue" class="input" type="email" wire:model="f.email"></x-field>
                <x-field label="Role" for="ur" name="f.role"><select id="ur" class="select" wire:model.live="f.role">@foreach (Perm::ROLES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-field>
                @if (in_array($f['role'], ['supplier', 'supplier_worker']))
                    <x-field label="Supplier company" for="us" name="f.supplier_id"><select id="us" class="select" wire:model="f.supplier_id"><option value="">Choose…</option>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
                @else
                    <x-field label="Department" for="ud" name="f.department" :opt="true"><input id="ud" class="input" wire:model="f.department"></x-field>
                @endif
            </form>
            @unless ($f['id'])<p class="help" style="margin-top:10px">The person gets an email with a link to set their own password. Nobody else ever knows it.</p>@endunless
            <x-slot:foot><button type="button" class="btn" wire:click="close">Cancel</button><button type="submit" form="userForm" class="btn btn-p">{{ $f['id'] ? 'Save user' : 'Send invitation' }}</button></x-slot:foot>
        </x-modal>
    @endif
</div>
