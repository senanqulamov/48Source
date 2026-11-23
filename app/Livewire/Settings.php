<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    use Page;

    public string $name = '';

    public string $phone = '';

    public string $locale = 'en';

    public bool $two_factor = false;

    public bool $email_notices = true;

    public string $current = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = $this->me()->name;
        $this->phone = (string) $this->me()->phone;
        $this->locale = $this->me()->locale;
        $this->two_factor = $this->me()->two_factor;
        $this->email_notices = $this->me()->email_notices;
    }

    public function saveProfile()
    {
        $data = $this->validate(['name' => 'required|string|min:2|max:120', 'phone' => 'nullable|string|max:40', 'locale' => 'required|in:' . implode(',', array_keys(config('procurement.locales')))]);
        $changed = $this->me()->locale !== $data['locale'];
        $this->me()->update(['name' => trim($data['name']), 'phone' => $data['phone'] ?: null, 'locale' => $data['locale']]);
        if ($changed) {
            session()->flash('toast', 'Profile saved.');

            return $this->redirectRoute('settings');
        }
        $this->toast('Profile saved.');
    }

    public function saveSecurity(): void
    {
        $this->me()->update(['two_factor' => $this->two_factor, 'email_notices' => $this->email_notices]);
        \App\Support\Audit::log('Auth', $this->two_factor ? 'Sign-in code by email is on' : 'Sign-in code by email is off');
        $this->toast('Preferences saved.');
    }

    public function savePassword(): void
    {
        $this->validate(['current' => 'required', 'password' => ['required', 'confirmed', 'different:current', Password::defaults()]], [], ['current' => 'current password', 'password' => 'new password']);
        if (! Hash::check($this->current, $this->me()->password)) {
            $this->addError('current', 'The current password is not correct.');

            return;
        }
        $this->me()->update(['password' => $this->password]);
        $this->reset('current', 'password', 'password_confirmation');
        Audit::log('Auth', 'Changed password');
        $this->toast('Password changed.');
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
