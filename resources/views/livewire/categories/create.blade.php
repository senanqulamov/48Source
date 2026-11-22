<div>
    <x-button :text="__('Create Category')" wire:click="$toggle('modal')" sm />

    @if($modal)
    <div class="scrim source-dialog" wire:key="resources-views-livewire-categories-create-blade-php-overlay" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Create New Market') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body"> $refs.name.focus(), 250)" size="md" blur="xl">
        <form id="market-create" wire:submit="save" class="space-y-4">

            <div>
                <x-input label="{{ __('Category Name') }} *" wire:model="name" required />
            </div>

        </form>
        
    </div>
            <div class="ov-f"><x-button type="submit" form="market-create">
                @lang('Save')
            </x-button></div>
        </section>
    </div>
@endif
</div>
