<div>
    @if($modal)
    <div class="scrim source-dialog source-drawer-scrim" wire:key="resources-views-livewire-seller-markets-update-blade-php-drawer" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="drawer source-admin-drawer" role="dialog" aria-modal="true" aria-label="{{ __('Edit details') }}">
            <div class="ov-h"><h2>{{ __('Update Market: #:id', ['id' => $market?->id]) }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-drawer-body">
        
        <form id="seller-market-update-{{ $market?->id }}" wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <x-input
                    label="{{ __('Name') }}"
                    wire:model.blur="market.name"
                    required
                    hint="{{ __('Market name') }}"
                />

                <x-input
                    label="{{ __('Location') }}"
                    wire:model.blur="market.location"
                    hint="{{ __('Market location') }}"
                />

                <x-input
                    label="{{ __('Image Path') }}"
                    wire:model.blur="market.image_path"
                    hint="{{ __('URL or path to image') }}"
                />
            </div>

            <x-button
                type="submit"
                form="seller-market-update-{{ $market?->id }}"
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
