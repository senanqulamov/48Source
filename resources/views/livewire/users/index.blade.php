<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Users and organizations') }}</h1><p class="ph-d">{{ __('Manage account roles, supplier status and access permissions.') }}</p></div>
        <div class="ph-a"><livewire:users.create @created="$refresh" /></div>
    </header>
    <section class="card"><div class="dt">
        <div class="dt-top">
            <label class="dt-search"><x-source.icon name="search" :size="16" /><span class="sr">{{ __('Search users') }}</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name or email') }}"></label>
            <div class="dt-tools">
                <label for="users-role" class="sr">{{ __('Role') }}</label>
                <select id="users-role" class="select" wire:model.live="roleFilter">
                    <option value="all">{{ __('All roles') }}</option><option value="admin">{{ __('Admin') }}</option>
                    <option value="buyer">{{ __('Buyer') }}</option><option value="seller">{{ __('Seller') }}</option>
                    <option value="supplier">{{ __('Supplier') }}</option>
                </select>
                <label for="users-rows" class="sr">{{ __('Rows') }}</label>
                <select id="users-rows" class="select" wire:model.live="quantity">
                    <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                </select>
                @if(\Illuminate\Support\Facades\Route::has('export.suppliers.excel'))
                    <a href="{{ route('export.suppliers.excel') }}" class="btn btn-sm">{{ __('Export suppliers') }}</a>
                @endif
            </div>
        </div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('User') }}</th><th>{{ __('Company') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($this->rows as $person)
                <tr wire:key="user-{{ $person->id }}">
                    <td><a href="{{ route('users.show', $person) }}" class="t1 link">{{ $person->name }}</a><div class="t2">{{ $person->email }}</div></td>
                    <td class="sm">{{ $person->company_name ?: '—' }}</td>
                    <td><div class="row wrap">
                        @foreach($person->roles as $role)
                            <span class="badge">{{ $role->display_name ?: ucfirst($role->name) }}</span>
                        @endforeach
                    </div></td>
                    <td><span class="badge {{ $person->is_active ? 'b-ok' : 'b-bad' }}">{{ $person->is_active ? __('Active') : __('Inactive') }}</span>
                        @if($person->isSupplier())<div class="t2">{{ ucfirst($person->supplier_status ?: 'pending') }} {{ __('supplier') }}</div>@endif</td>
                    <td class="sm mono">{{ $person->created_at?->format('d M Y') ?? '—' }}</td>
                    <td><div class="row">
                        @can('edit_users')
                            <button type="button" class="btn btn-sm" wire:click="$dispatch('load::user', { user: '{{ $person->id }}' })">{{ __('Edit') }}</button>
                        @endcan
                        @can('delete_users')
                            <livewire:users.delete :user="$person" :key="'users-delete-'.$person->id" @deleted="$refresh" />
                        @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><h3>{{ __('No users match these filters') }}</h3></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="dt-foot">{{ $this->rows->links() }}</div>
    </div></section>
    <livewire:users.update @updated="$refresh" />
</div>