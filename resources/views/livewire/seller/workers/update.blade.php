<div>
    @if($modal)
    <div class="scrim source-dialog" wire:key="resources-views-livewire-seller-workers-update-blade-php-overlay" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="modal wide" role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Update Worker') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body">
        <form id="seller-worker-update" wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="{{ __('Full Name') }} *" wire:model="worker.name" required />
                <x-input label="{{ __('Email Address') }} *" wire:model="worker.email" required />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-password
                    label="{{ __('New Password') }}"
                    wire:model="password"
                    rules
                    generator
                    x-on:generate="$wire.set('password_confirmation', $event.detail.password)"
                    hint="{{ __('Leave empty to keep current password') }}"
                />
                <x-password
                    label="{{ __('Confirm Password') }}"
                    wire:model="password_confirmation"
                    rules
                />
            </div>

            <div>
                <x-select.styled
                    :label="__('Assign Markets')"
                    wire:model="marketIds"
                    :options="$markets"
                    select="label:name|value:id"
                    searchable
                    multiple
                    :placeholder="__('Select markets')"
                />
            </div>
        </form>

        
    </div>
            <div class="ov-f"><div class="flex items-center justify-between w-full">
                <x-button color="red" wire:click="delete">
                    @lang('Delete')
                </x-button>

                <x-button type="submit" form="seller-worker-update">
                    @lang('Save')
                </x-button>
            </div></div>
        </section>
    </div>
@endif
</div>
