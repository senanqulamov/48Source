<div>
    @if($modal)
    <div class="scrim source-dialog source-drawer-scrim" wire:key="resources-views-livewire-categories-update-blade-php-drawer" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="drawer source-admin-drawer" role="dialog" aria-modal="true" aria-label="{{ __('Edit details') }}">
            <div class="ov-h"><h2>{{ __('Update Category: #:name', ['name' => $category?->name]) }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-drawer-body">
        
        <form id="category-update-{{ $category?->id }}" wire:submit="save" class="space-y-6">

            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                <x-input
                    label="{{ __('Name') }}"
                    wire:model.blur="category.name"
                    required
                    hint="{{ __('Product Category Name') }}"
                />
            </div>

            <x-button
                type="submit"
                form="category-update-{{ $category?->id }}"
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
