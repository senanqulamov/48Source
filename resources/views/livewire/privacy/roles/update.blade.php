<div>
    @if($modal)
    <div class="scrim source-dialog source-drawer-scrim" wire:key="resources-views-livewire-privacy-roles-update-blade-php-drawer" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="drawer source-admin-drawer" role="dialog" aria-modal="true" aria-label="{{ __('Edit details') }}">
            <div class="ov-h"><h2>{{ __('Update Role: #:id', ['id' => $role?->id]) }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-drawer-body">
        
        <form id="role-update-{{ $role?->id }}" wire:submit="save" class="space-y-6">

            <div class="grid grid-cols-1 gap-4">
                <x-input
                    label="{{ __('Role Name') }}"
                    wire:model.blur="role.name"
                    required
                    hint="{{ __('Lowercase, alphanumeric with dashes/underscores only') }}"
                />

                <x-input
                    label="{{ __('Display Name') }}"
                    wire:model.blur="role.display_name"
                    required
                    hint="{{ __('Human-readable name') }}"
                />

                <x-textarea
                    label="{{ __('Description') }}"
                    wire:model.blur="role.description"
                    hint="{{ __('Brief description of this role') }}"
                    rows="3"
                />
            </div>

            <x-button
                type="submit"
                form="role-update-{{ $role?->id }}"
                color="primary"
                loading="save"
                icon="check"
            >
                {{ __('Save Changes') }}
            </x-button>
        </form>
    </div>
            
        </section>
    </div>
@endif
</div>
