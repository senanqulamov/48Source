<?php

namespace App\Livewire\Users;

use App\Livewire\Traits\Alert;
use App\Livewire\Traits\WithLogging;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Delete extends Component
{
    use Alert, WithLogging;

    public User $user;

    public bool $confirming = false;

    public function render(): View
    {
        return view('livewire.users.delete');
    }

    public function confirm(): void
    {
        // Check permission
        if (!Auth::check() || !Auth::user()->hasPermission('delete_users')) {
            $this->error('You do not have permission to delete users.');
            return;
        }

        $this->confirming = true;
    }

    public function delete(): void
    {
        abort_unless($this->confirming, 403);
        // Check permission
        if (!Auth::check() || !Auth::user()->hasPermission('delete_users')) {
            $this->error('You do not have permission to delete users.');
            return;
        }

        // Store user data before deletion
        $userData = [
            'name' => $this->user->name,
            'email' => $this->user->email,
        ];
        $userId = $this->user->id;
        $this->user->delete();

        // Log the deletion
        $this->logDelete(User::class, $userId, $userData);


        $this->confirming = false;
        $this->dispatch('deleted');

        $this->success();
    }
}
