<div>
    <button type="button" class="btn btn-p" wire:click="$set('modal', true)">
        <x-source.icon name="plus" :size="15" />{{ __('Create New User') }}
    </button>

    @if($modal)
        <div class="scrim source-dialog" wire:key="create-user-dialog"
             wire:keydown.escape="$set('modal', false)" wire:click.self="$set('modal', false)">
            <section class="modal wide" role="dialog" aria-modal="true" aria-labelledby="create-user-title">
                <div class="ov-h">
                    <h2 id="create-user-title">{{ __('Create New User') }}</h2>
                    <span class="sp"></span>
                    <button type="button" class="icon-btn" wire:click="$set('modal', false)" aria-label="{{ __('Close') }}"><x-source.icon name="x" /></button>
                </div>
                <form id="user-create" wire:submit="save" class="source-dialog-form">
                    <div class="ov-b stack">
                        <div class="note">{{ __('Create an account and choose its authorized role(s). Additional company details can be edited afterward.') }}</div>
                        @if($errors->any())
                            <div class="note bad" role="alert">
                                <strong>{{ __('Please correct the highlighted fields.') }}</strong>
                                @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
                            </div>
                        @endif
                        <div class="fgrid">
                            <div class="field">
                                <label for="new-user-name">{{ __('Full Name') }} *</label>
                                <input class="input" id="new-user-name" type="text" wire:model="name" autocomplete="name" maxlength="255" required autofocus />
                                @error('name')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="new-user-email">{{ __('Email Address') }} *</label>
                                <input class="input" id="new-user-email" type="email" wire:model="email" autocomplete="off" maxlength="255" required />
                                @error('email')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="new-user-password">{{ __('Password') }} *</label>
                                <input class="input" id="new-user-password" type="password" wire:model="password" autocomplete="new-password" minlength="8" required />
                                @error('password')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="new-user-confirmation">{{ __('Confirm Password') }} *</label>
                                <input class="input" id="new-user-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" minlength="8" required />
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-h"><h3 class="card-t">{{ __('User roles') }} *</h3></div>
                            <div class="card-b">
                                <div class="row wrap" style="gap:18px">
                                    @foreach($roles as $role)
                                        <label class="row" style="gap:8px;cursor:pointer" wire:key="new-role-{{ $role->id }}">
                                            <input class="chk" type="checkbox" wire:model="roleIds" value="{{ $role->id }}">
                                            <span>{{ $role->display_name ?? ucfirst($role->name) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('roleIds')<span class="err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="ov-f">
                        <button type="button" class="btn" wire:click="$set('modal', false)">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="save">
                            <x-source.icon name="check" :size="14" />{{ __('Create user') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>
