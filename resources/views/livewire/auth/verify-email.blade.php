<div>
    <h1 class="auth-h">Confirm your email</h1>
    <p class="auth-d">We sent a confirmation link to <b>{{ auth()->user()->email }}</b>. Open it to activate your account. The link works for 60 minutes.</p>
    @if ($resent)<div style="margin-bottom:14px"><x-note kind="ok">A new link has been sent.</x-note></div>@endif
    <div class="auth-form">
        <button class="btn btn-p" type="button" wire:click="resend" wire:loading.attr="disabled">Send the link again</button>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn" type="submit" style="width:100%">Sign out</button></form>
    </div>
</div>
