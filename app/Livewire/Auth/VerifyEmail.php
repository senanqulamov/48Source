<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Confirm your email')]
class VerifyEmail extends Component
{
    public bool $resent = false;

    public function mount()
    {
        if (Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
    }

    public function resend(): void
    {
        Auth::user()->sendEmailVerificationNotification();
        $this->resent = true;
    }

    public function render()
    {
        return view('livewire.auth.verify-email');
    }
}
