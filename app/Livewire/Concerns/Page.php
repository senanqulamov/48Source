<?php

namespace App\Livewire\Concerns;

use App\Exceptions\BusinessRule;
use App\Models\User;
use App\Support\Translator;
use Illuminate\Support\Facades\Auth;

/** Shared helpers for full-page components. */
trait Page
{
    public ?string $modal = null;

    protected function me(): User
    {
        return Auth::user();
    }

    /** Stop the action unless the role holds the ability. */
    protected function need(string $module, string $ability = 'V'): void
    {
        abort_unless($this->me()->allowed($module, $ability), 403, 'Your role is not allowed to do this.');
    }

    protected function toast(string $text, string $kind = 'check'): void
    {
        $this->dispatch('toast', text: Translator::text($text), kind: $kind);
    }

    /** Run a business action; a refused rule becomes a message instead of an error page. */
    protected function attempt(callable $fn, ?string $done = null): bool
    {
        try {
            $fn();
        } catch (BusinessRule $e) {
            $this->toast($e->getMessage(), 'alert');

            return false;
        }
        if ($done) {
            $this->toast($done);
        }

        return true;
    }

    public function open(string $modal): void
    {
        $this->resetErrorBag();
        $this->modal = $modal;
    }

    public function close(): void
    {
        $this->modal = null;
    }
}
