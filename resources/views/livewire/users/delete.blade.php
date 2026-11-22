<div>
    <button class="btn btn-sm btn-d" type="button" aria-label="{{ __('Delete user') }}" wire:click="confirm">
        <x-source.icon name="trash" :size="15" />
    </button>
    @if($confirming)
        <div class="scrim source-dialog" wire:key="delete-user-{{ $user->id }}"
             wire:keydown.escape="$set('confirming', false)" wire:click.self="$set('confirming', false)">
            <section class="modal" role="alertdialog" aria-modal="true"
                     aria-labelledby="delete-user-title-{{ $user->id }}"
                     aria-describedby="delete-user-copy-{{ $user->id }}">
                <div class="ov-h">
                    <h2 id="delete-user-title-{{ $user->id }}">{{ __('Delete user?') }}</h2>
                    <span class="sp"></span>
                    <button class="icon-btn" type="button" wire:click="$set('confirming', false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button>
                </div>
                <div class="ov-b stack">
                    <div class="note bad">{{ __('This action cannot be undone.') }}</div>
                    <p id="delete-user-copy-{{ $user->id }}">{{ __('Delete :name (:email)?', ['name' => $user->name, 'email' => $user->email]) }}</p>
                </div>
                <div class="ov-f">
                    <button class="btn" type="button" wire:click="$set('confirming', false)">{{ __('Cancel') }}</button>
                    <button class="btn btn-d solid" type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">{{ __('Delete user') }}</button>
                </div>
            </section>
        </div>
    @endif
</div>