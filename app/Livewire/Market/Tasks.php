<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Models\MarketOrder;
use App\Models\WorkerTask;
use App\Services\MarketService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tasks')]
class Tasks extends Component
{
    use Page;

    #[Url(except: 'open')]
    public string $tab = 'open';

    public function mount(): void
    {
        abort_unless(in_array($this->me()->role, ['seller', 'market_worker'], true) && $this->me()->store_id, 403, 'Your account is not linked to a store yet. Ask an administrator.');
    }

    public function done(MarketService $service, int $id): void
    {
        $this->need('marketplace', 'E');
        $task = WorkerTask::where('store_id', $this->me()->store_id)->where('status', 'open')->findOrFail($id);
        $this->attempt(fn () => $service->advance($task->order, $this->me()), $task->type === 'pick' ? 'Picked. A delivery task was created.' : 'Delivered. The buyer was told.');
    }

    public function render()
    {
        $base = fn () => WorkerTask::where('store_id', $this->me()->store_id);

        return view('livewire.market.tasks', ['tasks' => $base()->where('status', $this->tab === 'done' ? 'done' : 'open')->with('order.items', 'order.buyer', 'order.supplier', 'doer')->orderBy($this->tab === 'done' ? 'done_at' : 'id', $this->tab === 'done' ? 'desc' : 'asc')->limit(100)->get(),
            'open' => $base()->where('status', 'open')->count(), 'doneCount' => $base()->where('status', 'done')->count()]);
    }
}
