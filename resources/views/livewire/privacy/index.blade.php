<div class="stack">
<header class="ph"><div><h1 class="ph-t">{{ __('Roles & permissions') }}</h1>
    <p class="ph-d">{{ __('Manage who can view, create, edit and approve records across 48Source.') }}</p></div></header>
<div class="tabs" role="tablist" aria-label="{{ __('Access control sections') }}">
    <button class="tab {{ $tab === 'users' ? 'on' : '' }}" type="button" wire:click="$set('tab','users')">{{ __('Users & roles') }}</button>
    <button class="tab {{ $tab === 'roles' ? 'on' : '' }}" type="button" wire:click="$set('tab','roles')">{{ __('Roles') }}</button>
    <button class="tab {{ $tab === 'permissions' ? 'on' : '' }}" type="button" wire:click="$set('tab','permissions')">{{ __('Permission groups') }}</button>
</div>
@if($tab === 'users')
    <section class="card"><div class="dt">
        <div class="dt-top"><label class="dt-search"><x-source.icon name="search" :size="16" />
                <span class="sr">{{ __('Search users') }}</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name or email') }}"></label>
            <div class="dt-tools"><label for="access-role-filter" class="sr">{{ __('Role') }}</label>
                <select id="access-role-filter" class="select" wire:model.live="roleFilter">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach($this->roles as $role)<option value="{{ $role->id }}">{{ $role->display_name ?: $role->name }}</option>@endforeach
                </select>
            </div></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('User') }}</th><th>{{ __('Assigned roles') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Action') }}</th></tr></thead>
            <tbody>@forelse($this->rows as $person)
                <tr wire:key="access-user-{{ $person->id }}">
                    <td><a class="t1 link" href="{{ route('privacy.users.show',$person) }}">{{ $person->name }}</a><div class="t2">{{ $person->email }}</div></td>
                    <td><div class="row wrap">@foreach($person->roles as $role)<span class="badge">{{ $role->display_name ?: $role->name }}</span>@endforeach</div></td>
                    <td class="mono sm">{{ $person->created_at?->format('d M Y') }}</td>
                    <td><button type="button" class="btn btn-sm" wire:click="$dispatch('load::user-roles', { user: '{{ $person->id }}' })">{{ __('Manage roles') }}</button></td>
                </tr>
            @empty<tr><td colspan="4"><div class="empty">{{ __('No users found') }}</div></td></tr>@endforelse</tbody>
        </table></div><div class="dt-foot">{{ $this->rows->links() }}</div>
    </div></section>
@elseif($tab === 'roles')
    <section class="card">
        <div class="card-h"><h2 class="card-t">{{ __('Role definitions') }}</h2></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Description') }}</th><th class="num">{{ __('Users') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>@forelse($this->roles as $role)
                <tr><td class="t1">{{ $role->display_name ?: $role->name }}</td><td class="muted sm">{{ $role->description ?: '—' }}</td>
                    <td class="num mono">{{ $role->users_count }}</td>
                    <td><div class="row">
                        <button type="button" class="btn btn-sm" wire:click="$dispatch('load::role-permissions', { role: '{{ $role->id }}' })">{{ __('Permissions') }}</button>
                        @unless($role->is_system)
                            <button type="button" class="btn btn-sm" wire:click="$dispatch('load::role', { role: '{{ $role->id }}' })">{{ __('Edit') }}</button>
                            <livewire:privacy.roles.delete :role="$role" :key="'access-role-delete-'.$role->id" @deleted="$refresh" />
                        @endunless
                    </div></td>
                </tr>
            @empty<tr><td colspan="4"><div class="empty">{{ __('No roles defined') }}</div></td></tr>@endforelse</tbody>
        </table></div>
    </section>
@else
    <div class="stack">@foreach($this->permissionsByGroup as $group => $permissions)
        <section class="card"><div class="card-h"><h2 class="card-t">{{ ucfirst($group ?: __('Other')) }}</h2></div>
            <div class="card-b"><div class="grid g-2-1">@foreach($permissions as $permission)
                <div class="row"><span class="badge">{{ $permission->name }}</span><span class="muted sm">{{ $permission->display_name ?: $permission->description }}</span></div>
            @endforeach</div></div>
        </section>
    @endforeach</div>
@endif
<livewire:privacy.user-roles.update @updated="$refresh" />
<livewire:privacy.role-permissions.update @updated="$refresh" />
<livewire:privacy.roles.update @updated="$refresh" />
</div>