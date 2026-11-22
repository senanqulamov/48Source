<div>
    <x-button :text="__('Create New Field Supplier')" wire:click="$toggle('modal')" sm/>

    @if($modal)
    <div class="scrim source-dialog" wire:key="resources-views-livewire-supplier-workers-create-blade-php-overlay" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Create New Field Supplier') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body"> $refs.name.focus(), 250)" size="md" blur="xl">
        <form id="worker-create" wire:submit="save" class="space-y-4">
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
                label="{{ __('Password') }}"
                type="password"
                wire:model="password"
                placeholder="{{ __('Enter password') }}"
            />

            <x-input
                label="{{ __('Confirm Password') }}"
                type="password"
                wire:model="password_confirmation"
                placeholder="{{ __('Confirm password') }}"
            />
        </form>
        
    </div>
            <div class="ov-f"><x-button type="submit" form="worker-create">
                @lang('Save')
            </x-button></div>
        </section>
    </div>
@endif
</div>
