<div>
    @if($modal)
    <div class="scrim source-dialog" wire:key="resources-views-livewire-seller-workers-create-blade-php-overlay" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Create Worker') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body"> $refs.name.focus(), 250)"
        size="2xl"
        blur="xl"
        x-on:seller::workers::create::open.window="show = true"
    >
        <form id="seller-worker-create" wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="{{ __('Full Name') }} *" x-ref="name" wire:model="worker.name" required />
                <x-input label="{{ __('Email Address') }} *" wire:model="worker.email" required />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-password
                    label="{{ __('Password') }} *"
                    wire:model="password"
                    rules
                    generator
                    x-on:generate="$wire.set('password_confirmation', $event.detail.password)"
                    required
                />
                <x-password
                    label="{{ __('Confirm Password') }} *"
                    wire:model="password_confirmation"
                    rules
                    required
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
                <div class="text-xs text-gray-500 mt-1">{{ __('A worker can be assigned to multiple markets.') }}</div>
            </div>
        </form>

        
    </div>
            <div class="ov-f"><x-button type="submit" form="seller-worker-create">
                @lang('Save')
            </x-button></div>
        </section>
    </div>
@endif
</div>
