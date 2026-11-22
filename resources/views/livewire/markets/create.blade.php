<div>
    <x-button :text="__('Create New Market')" wire:click="$toggle('modal')" sm />

    @if($modal)
    <div class="scrim source-dialog" wire:key="resources-views-livewire-markets-create-blade-php-overlay" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Create New Market') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body"> $refs.name.focus(), 250)" size="md" blur="xl">
        <form id="market-create" wire:submit="save" class="space-y-4">
            <div>
                <x-select.native
                    label="{{ __('Owner (Seller)') }} *"
                    wire:model="market.user_id"
                    :options="$sellers"
                    select="label:name|value:id"
                    required
                />
            </div>

            <div>
                <x-input label="{{ __('Name') }} *" x-ref="name" wire:model="market.name" required />
            </div>

            <div>
                <x-input label="{{ __('Location') }}" wire:model="market.location" />
            </div>

            <div>
                <x-input label="{{ __('Image Path') }}" wire:model="market.image_path" hint="{{ __('URL or path to market image') }}" />
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
