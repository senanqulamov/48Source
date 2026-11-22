<div>
    <x-button :text="__('Create New Role')" wire:click="$toggle('modal')" icon="plus" sm />

    @if($modal)
    <div class="scrim source-dialog" wire:key="resources-views-livewire-privacy-roles-create-blade-php-overlay" wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
        <section class="modal " role="dialog" aria-modal="true" aria-label="{{ __('Dialog') }}">
            <div class="ov-h"><h2>{{ __('Create New Role') }}</h2><span class="sp"></span><button type="button" class="icon-btn" wire:click="$set('modal',false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button></div>
            <div class="ov-b source-modal-body"> $refs.name.focus(), 250)" size="md" blur="xl">
        <form id="role-create" wire:submit="save" class="space-y-4">
            <div>
                <x-input
                    label="{{ __('Role Name') }} *"
                    x-ref="name"
                    wire:model="role.name"
                    required
                    hint="{{ __('Lowercase, alphanumeric with dashes/underscores only (e.g., custom_role)') }}"
                />
            </div>

            <div>
                <x-input
                    label="{{ __('Display Name') }} *"
                    wire:model="role.display_name"
                    required
                    hint="{{ __('Human-readable name (e.g., Custom Role)') }}"
                />
            </div>

            <div>
                <x-textarea
                    label="{{ __('Description') }}"
                    wire:model="role.description"
                    hint="{{ __('Brief description of this role') }}"
                    rows="3"
                />
            </div>
        </form>
        
    </div>
            <div class="ov-f"><x-button type="submit" form="role-create" loading="save">
                @lang('Save')
            </x-button></div>
        </section>
    </div>
@endif
</div>
