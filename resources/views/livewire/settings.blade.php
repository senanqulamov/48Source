<div>
    <x-ph title="Settings" desc="Your profile and sign-in details." :crumbs="[['Settings']]" />
    <div class="grid g-2">
        <x-card title="Profile" :sub="auth()->user()->roleLabel()">
            <form class="stack" wire:submit="saveProfile" novalidate>
                <x-field label="Full name" for="sname" name="name"><input id="sname" class="input" wire:model="name" required></x-field>
                <x-field label="Work email" for="semail" help="Ask an administrator to change your email."><input id="semail" class="input" value="{{ auth()->user()->email }}" disabled></x-field>
                <x-field label="Phone" for="sphone" name="phone" :opt="true"><input id="sphone" class="input" wire:model="phone"></x-field>
                <x-field label="Language" for="slocale" name="locale" help="Menus and common buttons change. Page texts stay in English."><select id="slocale" class="select" wire:model="locale">@foreach (config('procurement.locales') as $code => $n)<option value="{{ $code }}">{{ $n }}</option>@endforeach</select></x-field>
                <div><button class="btn btn-p" type="submit" wire:loading.attr="disabled">Save profile</button></div>
            </form>
        </x-card>
        <x-card title="Sign-in security and notifications">
            <div class="stack" style="gap:14px">
                <label class="row" style="cursor:pointer;align-items:flex-start"><input type="checkbox" class="chk" wire:model="two_factor" style="margin-top:3px"><span><b style="display:block">Ask for a code by email when I sign in</b><span class="muted sm">After your password, a six-digit code is sent to {{ auth()->user()->email }}. It is valid for 10 minutes.</span></span></label>
                <label class="row" style="cursor:pointer;align-items:flex-start"><input type="checkbox" class="chk" wire:model="email_notices" style="margin-top:3px"><span><b style="display:block">Send me notifications by email too</b><span class="muted sm">You always get them inside 48Source. This adds an email copy.</span></span></label>
                <div class="row"><a class="link sm" href="{{ route('privacy') }}" wire:navigate>How your data is used</a><span class="sp"></span><button type="button" class="btn btn-p" wire:click="saveSecurity">Save preferences</button></div>
            </div>
        </x-card>
        <x-card title="Password" sub="At least 10 characters, with letters and numbers">
            <form class="stack" wire:submit="savePassword" novalidate>
                <x-field label="Current password" for="pcur" name="current"><input id="pcur" class="input" type="password" wire:model="current" autocomplete="current-password"></x-field>
                <x-field label="New password" for="pnew" name="password"><input id="pnew" class="input" type="password" wire:model="password" autocomplete="new-password"></x-field>
                <x-field label="Repeat new password" for="pnew2"><input id="pnew2" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password"></x-field>
                <div><button class="btn btn-p" type="submit" wire:loading.attr="disabled">Change password</button></div>
            </form>
        </x-card>
    </div>
</div>
