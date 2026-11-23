<div>
    <h1 class="auth-h">Sign in</h1>
    <p class="auth-d">Use your work email. Your role decides what you see after signing in.</p>
    @if (session('status'))<div style="margin-bottom:14px"><x-note kind="ok">{{ session('status') }}</x-note></div>@endif
    <form class="auth-form" wire:submit="login" novalidate>
        <x-field label="Work email" for="email" name="email"><input id="email" class="input" type="email" wire:model="email" autocomplete="username" autofocus required></x-field>
        <x-field label="Password" for="password" name="password"><input id="password" class="input" type="password" wire:model="password" autocomplete="current-password" required></x-field>
        <label class="row" style="gap:8px;cursor:pointer"><input type="checkbox" class="chk" wire:model="remember"><span>Keep me signed in on this device</span></label>
        <button class="btn btn-p" type="submit" wire:loading.attr="disabled"><span wire:loading.remove wire:target="login">Sign in</span><span wire:loading wire:target="login">Signing in…</span></button>
    </form>
    <div class="auth-links">
        <a class="link" href="{{ route('password.request') }}" wire:navigate>Forgot your password?</a>
        @if (\App\Models\FeatureFlag::isOn('supplier_registration'))<a class="link" href="{{ route('register') }}" wire:navigate>Register as a supplier</a>@endif
    </div>
</div>
