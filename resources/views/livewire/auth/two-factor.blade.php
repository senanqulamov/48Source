<div>
    <h1 class="auth-h">Enter your sign-in code</h1>
    <p class="auth-d">We sent a six-digit code to <b>{{ $email }}</b>. It is valid for 10 minutes.</p>
    <form class="auth-form" wire:submit="verify" novalidate>
        <x-field label="Code" for="code" name="code"><input id="code" class="input mono" inputmode="numeric" autocomplete="one-time-code" maxlength="7" wire:model="code" autofocus style="font-size:20px;letter-spacing:4px"></x-field>
        <button class="btn btn-p" type="submit" wire:loading.attr="disabled">Sign in</button>
    </form>
    <div class="auth-links"><button type="button" class="link" wire:click="resend">Send a new code</button><a class="link" href="{{ route('login') }}" wire:navigate>Back to sign in</a></div>
</div>
