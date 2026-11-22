<div>
    @if($modal)
    <div class="scrim source-dialog source-drawer-scrim" wire:key="resources-views-livewire-supplier-workers-update-blade-php-drawer" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="drawer source-admin-drawer" role="dialog" aria-modal="true" aria-label="{{ __('Edit details') }}">
            <div class="ov-h"><h2>{{ __('Update Worker: #:id', ['id' => $worker?->id]) }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-drawer-body">
        
        <form id="worker-update-{{ $worker?->id }}" wire:submit="save" class="space-y-6">

            <div class="space-y-4">
                <x-input
                    label="{{ __('Name') }}"
                    wire:model="worker.name"
                    placeholder="{{ __('Enter worker name') }}"
                />

                <x-input
                    label="{{ __('Email') }}"
                    type="email"
                    wire:model="worker.email"
                    placeholder="{{ __('Enter email address') }}"
                />

                <x-input
                    label="{{ __('New Password') }}"
                    type="password"
                    wire:model="password"
                    placeholder="{{ __('Leave blank to keep current password') }}"
                    hint="{{ __('Leave blank to keep current password') }}"
                />

                <x-input
                    label="{{ __('Confirm Password') }}"
                    type="password"
                    wire:model="password_confirmation"
                    placeholder="{{ __('Confirm new password') }}"
                />
            </div>

            <x-button
                type="submit"
                form="worker-update-{{ $worker?->id }}"
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
