<?php

namespace App\Livewire\Seller\Tasks;

use App\Models\Market;
use App\Models\MarketWorkerTask;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $creating = false;
    public ?int $marketId = null;
    public ?int $workerId = null;
    public string $title = '';
    public string $details = '';
    public string $type = 'order';
    public ?string $dueAt = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSeller(), 403);
    }

    protected function rules(): array
    {
        return [
            'marketId' => ['required', 'integer', Rule::exists('markets', 'id')->where('user_id', auth()->id())],
            'workerId' => ['required', 'integer', Rule::exists('users', 'id')->where('seller_id', auth()->id())],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'details' => ['nullable', 'string', 'max:3000'],
            'type' => ['required', Rule::in(MarketWorkerTask::TYPES)],
            'dueAt' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function createTask(): void
    {
        abort_unless(auth()->user()?->isSeller(), 403);
        $data = $this->validate();
        DB::transaction(function () use ($data) {
            $market = Market::query()->where('user_id', auth()->id())
                ->lockForUpdate()->findOrFail($data['marketId']);

            $worker = User::query()->whereKey($data['workerId'])
                ->where('seller_id', auth()->id())
                ->whereHas('roles', fn ($query) => $query->where('name', 'market_worker'))
                ->whereHas('workerMarkets', fn ($query) => $query->where('markets.id', $market->id))
                ->first();

            // The selected worker, not any worker in the market, must be eligible.
            if (! $worker || $worker->id !== (int) $data['workerId']) {
                throw ValidationException::withMessages([
                    'workerId' => 'Assign a market worker who belongs to your team and this market.',
                ]);
            }

            MarketWorkerTask::create([
                'market_id' => $market->id,
                'seller_id' => auth()->id(),
                'worker_id' => $worker->id,
                'title' => trim($data['title']),
                'details' => trim($data['details'] ?? ''),
                'type' => $data['type'],
                'due_at' => $data['dueAt'] ?: null,
            ]);
        });

        $this->reset(['marketId', 'workerId', 'title', 'details', 'dueAt', 'creating']);
        $this->type = 'order';
        $this->resetPage();
        $this->dispatch('task-created');
    }

    public function removeTask(int $id): void
    {
        abort_unless(auth()->user()?->isSeller(), 403);
        $task = MarketWorkerTask::query()->where('seller_id', auth()->id())
            ->whereNull('completed_at')->findOrFail($id);
        $task->delete();
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->isSeller(), 403);

        $markets = Market::query()->where('user_id', auth()->id())
            ->orderBy('name')->get(['id', 'name']);
        $workers = User::query()->where('seller_id', auth()->id())
            ->whereHas('roles', fn ($query) => $query->where('name', 'market_worker'))
            ->whereHas('workerMarkets', fn ($query) => $query->whereIn('markets.id', $markets->pluck('id')))
            ->orderBy('name')->get(['id', 'name']);

        $tasks = MarketWorkerTask::query()->where('seller_id', auth()->id())
            ->with(['market:id,name', 'worker:id,name'])->orderByRaw('completed_at IS NOT NULL')
            ->orderBy('due_at')->latest('id')->paginate(15);

        return view('livewire.seller.tasks.index', compact('markets', 'workers', 'tasks'))
            ->layout('layouts.app');
    }
}
