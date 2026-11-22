<div class="stack">
    <header class="ph">
        <div>
            <div class="row sm muted"><a href="{{ route('privacy.index') }}" class="link">{{ __('Roles & permissions') }}</a><span>/</span>{{ $role->name }}</div>
            <h1 class="ph-t">{{ $role->display_name ?: ucfirst($role->name) }}</h1>
            <p class="ph-d">{{ $role->description ?: __('Review assigned permissions and accounts.') }}</p>
        </div>
        <div class="ph-a">
            @if($role->is_system)<span class="badge">{{ __('System role') }}</span>@endif
            <button type="button" class="btn btn-p" wire:click="$dispatch('load::role-permissions', { role: '{{ $role->id }}' })">{{ __('Manage permissions') }}</button>
            @unless($role->is_system)
                <button type="button" class="btn" wire:click="$dispatch('load::role', { role: '{{ $role->id }}' })">{{ __('Edit role') }}</button>
            @endunless
        </div>
    </header>
    <div class="facts"><div class="fact"><span>{{ __('Assigned users') }}</span><b class="mono">{{ $role->users_count ?? $role->users()->count() }}</b></div>
        <div class="fact"><span>{{ __('Permission groups') }}</span><b class="mono">{{ $this->permissionsByGroup->count() }}</b></div></div>
    <div class="grid g-2-1">
        <section class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Assigned permissions') }}</h2></div>
            <div class="card-b stack">
                @forelse($this->permissionsByGroup as $group => $permissions)
                    <div class="stack">
                        <h3 class="sm t1">{{ $group }}</h3>
                        <div class="row wrap">
                            @foreach($permissions as $permission)
                                <span class="badge" title="{{ $permission->description }}">{{ $permission->display_name ?: $permission->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="empty">{{ __('No permissions assigned to this role.') }}</div>
                @endforelse
            </div>
        </section>
        <aside class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Users with this role') }}</h2></div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th></tr></thead>
                <tbody>
                @forelse($this->rows as $person)
                    <tr><td><a class="link t1" href="{{ route('privacy.users.show',$person) }}">{{ $person->name }}</a></td>
                        <td class="sm">{{ $person->email }}</td></tr>
                @empty<tr><td colspan="2"><div class="empty">{{ __('No users assigned') }}</div></td></tr>@endforelse
                </tbody>
            </table></div>
            <div class="dt-foot">{{ $this->rows->links() }}</div>
        </aside>
    </div>
    <livewire:privacy.role-permissions.update @updated="$refresh"/>
    <livewire:privacy.roles.update @updated="$refresh"/>
</div>