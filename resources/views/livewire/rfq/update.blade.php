<div>
    @if($modal)
    <div class="scrim source-dialog source-drawer-scrim" wire:key="resources-views-livewire-rfq-update-blade-php-drawer" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="drawer source-admin-drawer" role="dialog" aria-modal="true" aria-label="{{ __('Edit details') }}">
            <div class="ov-h"><h2>{{ __('Update Request: #:id', ['id' => $request?->id]) }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-drawer-body">
        
        <form id="rfq-update-{{ $request?->id }}" wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input
                    label="{{ __('Title') }} *"
                    wire:model.blur="request.title"
                    required
                />

                <x-date
                    label="{{ __('Deadline') }} *"
                    wire:model.defer="request.deadline"
                    required
                />
            </div>

            <div>
                <x-textarea
                    label="{{ __('Description') }}"
                    wire:model.defer="request.description"
                    rows="4"
                />
            </div>

            <div class="border-t pt-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-md font-semibold text-[var(--color-text-high)]">
                        @lang('Items') *
                    </h3>
                    <x-button
                        type="button"
                        wire:click="addItem"
                        text="{{ __('Add Item') }}"
                        icon="plus"
                        sm
                    />
                </div>

                <div class="space-y-4">
                    @foreach($items as $index => $item)
                        <div
                            class="border border-[var(--color-border)] rounded-lg p-4 bg-[var(--color-surface-raised)]"
                            wire:key="rfq-update-item-{{ $index }}"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-5">
                                    <x-input
                                        label="{{ __('Product Name') }} *"
                                        wire:model="items.{{ $index }}.product_name"
                                        list="product-names-datalist-rfq-update"
                                        placeholder="{{ __('Type or select product name') }}"
                                        required
                                    />
                                </div>

                                <div class="md:col-span-3">
                                    <x-number
                                        label="{{ __('Quantity') }} *"
                                        wire:model="items.{{ $index }}.quantity"
                                        min="1"
                                        required
                                    />
                                </div>

                                <div class="md:col-span-3">
                                    <x-input
                                        label="{{ __('Specifications / Notes') }}"
                                        wire:model="items.{{ $index }}.specifications"
                                    />
                                </div>

                                <div class="md:col-span-1 flex justify-end">
                                    @if(count($items) > 1)
                                        <x-button.circle
                                            type="button"
                                            wire:click="removeItem({{ $index }})"
                                            icon="trash"
                                            color="red"
                                            xs
                                        />
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Datalist for product name autocomplete suggestions --}}
                    <datalist id="product-names-datalist-rfq-update">
                        @foreach($productNames as $productName)
                            <option value="{{ $productName }}">
                        @endforeach
                    </datalist>
                </div>
            </div>
        </form>

        
    </div>
            <div class="ov-f"><x-button type="submit" form="rfq-update-{{ $request?->id }}">
                @lang('Save Changes')
            </x-button></div>
        </section>
    </div>
@endif
</div>
