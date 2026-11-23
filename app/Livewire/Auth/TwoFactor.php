<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\Page;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Sign-in code')]
class TwoFactor extends Component
{
    public string $code = '';

    public function mount()
    {
        if (! session('2fa.user')) {
            return redirect()->route('login');
        }
    }

    /** Create a six-digit code, keep only its hash for ten minutes, and email it. */
    public static function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        Cache::put("2fa:{$user->id}", Hash::make($code), now()->addMinutes(10));
        Mail::raw("Your 48Source sign-in code is {$code}. It is valid for 10 minutes. If you did not try to sign in, change your password.", fn ($m) => $m->to($user->email)->subject('[48Source] Your sign-in code'));
    }

    public function resend(): void
    {
        $user = User::find(session('2fa.user'));
        $key = '2fa-send:' . ($user?->id ?? 0);
        if (! $user || RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('code', 'Too many codes requested. Wait a few minutes.');

            return;
        }
        RateLimiter::hit($key, 600);
        self::send($user);
        $this->dispatch('toast', text: 'A new code was sent to your email.', kind: 'check');
    }

    public function verify()
    {
        $user = User::find(session('2fa.user'));
        if (! $user || $user->status !== 'active') {
            return redirect()->route('login');
        }
        $key = "2fa-try:{$user->id}";
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', 'Too many wrong codes. Sign in again in ' . RateLimiter::availableIn($key) . ' seconds.');

            return null;
        }
        $hash = Cache::get("2fa:{$user->id}");
        if (! $hash || ! Hash::check(preg_replace('/\D/', '', $this->code), $hash)) {
            RateLimiter::hit($key, 300);
            $this->addError('code', $hash ? 'That code is not correct.' : 'The code has expired. Request a new one.');

            return null;
        }
        Cache::forget("2fa:{$user->id}");
        RateLimiter::clear($key);
        Auth::login($user, (bool) session('2fa.remember'));
        session()->forget(['2fa.user', '2fa.remember']);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('Auth', 'Signed in with an email code');

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.two-factor', ['email' => User::find(session('2fa.user'))?->email]);
    }
}
