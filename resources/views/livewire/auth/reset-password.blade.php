<div>
    <h1 class="auth-h">Choose a new password</h1>
    <p class="auth-d">At least 10 characters, with letters and numbers.</p>
    <form class="auth-form" wire:submit="save" novalidate>
        <x-field label="Work email" for="email" name="email"><input id="email" class="input" type="email" wire:model="email" required></x-field>
        <x-field label="New password" for="password" name="password"><input id="password" class="input" type="password" wire:model="password" autocomplete="new-password" autofocus required></x-field>
        <x-field label="Repeat new password" for="password2"><input id="password2" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password" required></x-field>
        <button class="btn btn-p" type="submit" wire:loading.attr="disabled">Save password</button>
    </form>
</div>
