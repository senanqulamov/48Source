<div class="stack">
<header class="ph"><div><h1 class="ph-t">{{ __('My profile') }}</h1><p class="ph-d">{{ __('Update your account, contact details and password.') }}</p></div>
<div class="ph-a"><span class="badge b-info">{{ auth()->user()->getRoles()[0] ?? __('Member') }}</span></div></header>
<form wire:submit="save" class="stack">
  <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Account details') }}</h2></div>
    <div class="card-b fgrid">
      <div class="field"><label for="profile-name">{{ __('Full name') }}</label><input id="profile-name" class="input" type="text" wire:model="user.name" />@error('user.name')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-email">{{ __('Email address') }}</label><input id="profile-email" class="input" type="email" wire:model="user.email" />@error('user.email')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-company_name">{{ __('Company') }}</label><input id="profile-company_name" class="input" type="text" wire:model="user.company_name" />@error('user.company_name')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-phone">{{ __('Phone') }}</label><input id="profile-phone" class="input" type="text" wire:model="user.phone" />@error('user.phone')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-mobile">{{ __('Mobile') }}</label><input id="profile-mobile" class="input" type="text" wire:model="user.mobile" />@error('user.mobile')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-website">{{ __('Website') }}</label><input id="profile-website" class="input" type="url" wire:model="user.website" />@error('user.website')<span class="err">{{ $message }}</span>@enderror</div>
    </div>
  </section>
  <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Address') }}</h2></div>
   <div class="card-b fgrid">
      <div class="field"><label for="profile-address_line1">{{ __('Address line 1') }}</label><input id="profile-address_line1" class="input" wire:model="user.address_line1" />@error('user.address_line1')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-address_line2">{{ __('Address line 2') }}</label><input id="profile-address_line2" class="input" wire:model="user.address_line2" />@error('user.address_line2')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-city">{{ __('City') }}</label><input id="profile-city" class="input" wire:model="user.city" />@error('user.city')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-state">{{ __('State or region') }}</label><input id="profile-state" class="input" wire:model="user.state" />@error('user.state')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-postal_code">{{ __('Postal code') }}</label><input id="profile-postal_code" class="input" wire:model="user.postal_code" />@error('user.postal_code')<span class="err">{{ $message }}</span>@enderror</div>
<div class="field"><label for="profile-country">{{ __('Country') }}</label><input id="profile-country" class="input" wire:model="user.country" />@error('user.country')<span class="err">{{ $message }}</span>@enderror</div>
   </div>
  </section>
  <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Change password') }}</h2></div>
    <div class="card-b fgrid">
       <div class="field"><label for="profile-password">{{ __('New password') }}</label><input id="profile-password" class="input" type="password" wire:model="password" autocomplete="new-password" />@error('password')<span class="err">{{ $message }}</span>@enderror</div>
       <div class="field"><label for="profile-password-confirmation">{{ __('Confirm new password') }}</label><input id="profile-password-confirmation" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password" /></div>
    </div>
  </section>
  <div class="row" style="justify-content:flex-end"><button class="btn btn-p" type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Save changes') }}</button></div>
</form>
</div>