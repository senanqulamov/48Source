<div>
    @if($modal)
    <div class="scrim source-dialog source-drawer-scrim" wire:key="resources-views-livewire-privacy-user-roles-update-blade-php-drawer" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="drawer source-admin-drawer" role="dialog" aria-modal="true" aria-label="{{ __('Edit details') }}">
            <div class="ov-h"><h2>{{ __('Update User Roles: :name', ['name' => $user?->name]) }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-drawer-body">
        
        <form id="user-roles-update-{{ $user?->id }}" wire:submit="save" class="space-y-6">

            <div class="space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    @lang('Select the roles for this user. Users can have multiple roles.')
                </p>

                @foreach($roles as $role)
                    <label class="flex items-center gap-3 p-4 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <x-checkbox
                            wire:model="selectedRoles"
                            value="{{ $role->id }}"
                            id="role-{{ $role->id }}"
                        />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $role->display_name }}
                                </span>
                                @if($role->is_system)
                                    <x-badge text="System" color="gray" sm />
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $role->description }}
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <x-button
                type="submit"
                form="user-roles-update-{{ $user?->id }}"
                color="primary"
                loading="save"
                icon="check"
            >
                {{ __('Save Roles') }}
            </x-button>
        </form>
    </div>
            
        </section>
    </div>
@endif
</div>
