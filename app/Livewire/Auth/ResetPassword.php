<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Choose a new password')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function save()
    {
        $this->validate(['email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::defaults()]]);
        $status = Password::reset(
            ['email' => mb_strtolower(trim($this->email)), 'password' => $this->password, 'password_confirmation' => $this->password_confirmation, 'token' => $this->token],
            function (User $user, string $password) {
                // An invited user proves ownership of the address by using the emailed link.
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'status' => $user->status === 'invited' ? 'active' : $user->status, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
                event(new PasswordReset($user));
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This link is not valid any more. Ask for a new one.']);
        }

        return redirect()->route('login')->with('status', 'Your password is set. Sign in with it now.');
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
