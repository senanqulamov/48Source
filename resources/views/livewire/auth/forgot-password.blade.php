<div>
    <h1 class="auth-h">Reset your password</h1>
    @if ($sent)
        <x-note kind="ok">If an account uses <b>{{ $email }}</b>, a link to choose a new password is on its way. It works for 60 minutes.</x-note>
    @else
        <p class="auth-d">Enter your work email and we will send a link to choose a new password.</p>
        <form class="auth-form" wire:submit="send" novalidate>
            <x-field label="Work email" for="email" name="email"><input id="email" class="input" type="email" wire:model="email" autofocus required></x-field>
            <button class="btn btn-p" type="submit" wire:loading.attr="disabled">Send reset link</button>
        </form>
    @endif
    <div class="auth-links"><a class="link" href="{{ route('login') }}" wire:navigate>Back to sign in</a></div>
</div>
