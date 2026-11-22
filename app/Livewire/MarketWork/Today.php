<?php

namespace App\Livewire\MarketWork;

use App\Models\Market;
use App\Models\MarketWorkerTask;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Today extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('market_worker'), 403);
    }

    public function toggle(int $id): void
    {
        abort_unless(auth()->user()?->hasRole('market_worker'), 403);
        DB::transaction(function () use ($id) {
            $task = MarketWorkerTask::query()->where('worker_id', auth()->id())
                ->lockForUpdate()->findOrFail($id);
            // Revoked market assignments cannot be used to complete tasks.
            abort_unless(Market::query()->whereKey($task->market_id)
                ->whereHas('workers', fn ($query) => $query->where('users.id', auth()->id()))
                ->exists(), 403);

            $task->update(['completed_at' => $task->completed_at ? null : now()]);
        });
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->hasRole('market_worker'), 403);

        $markets = Market::query()->whereHas('workers',
            fn ($query) => $query->where('users.id', auth()->id()))
            ->withCount('products')->orderBy('name')->get();
        $tasks = MarketWorkerTask::query()->where('worker_id', auth()->id())
            ->whereIn('market_id', $markets->pluck('id'))
            ->with('market:id,name')
            ->orderByRaw('completed_at IS NOT NULL')->orderBy('due_at')
            ->get();

        return view('livewire.market-work.today', compact('markets', 'tasks'))
            ->layout('layouts.app');
    }
}
