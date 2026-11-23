<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.mb_strtolower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        $user = User::where('email', mb_strtolower(trim($this->email)))->first();
        if ($user && $user->status === 'disabled') {
            throw ValidationException::withMessages(['email' => 'This account has been deactivated. Contact your administrator.']);
        }
        if ($user && $user->status === 'invited') {
            throw ValidationException::withMessages(['email' => 'Set your password first, using the link in your invitation email.']);
        }
        if ($user && $user->two_factor && $user->status === 'active' && Auth::validate(['email' => $user->email, 'password' => $this->password])) {
            RateLimiter::clear($key);
            session(['2fa.user' => $user->id, '2fa.remember' => $this->remember]);
            TwoFactor::send($user);

            return redirect()->route('two-factor');
        }
        if (! Auth::attempt(['email' => mb_strtolower(trim($this->email)), 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The email or password is not correct.']);
        }
        RateLimiter::clear($key);
        session()->regenerate();
        Auth::user()->forceFill(['last_login_at' => now()])->save();
        Audit::log('Auth', 'Signed in');

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
