<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\MarketOrder;
use App\Services\MarketService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Marketplace order')]
class OrderShow extends Component
{
    use Page;

    public string $number;

    public string $reason = '';

    public function mount(string $number): void
    {
        $this->number = $number;
        $this->order();
    }

    protected function order(): MarketOrder
    {
        return MarketOrder::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function advance(MarketService $service): void
    {
        $this->need('marketplace', 'E');
        $this->attempt(fn () => $service->advance($this->order(), $this->me()), 'Order updated. The buyer was told.');
    }

    public function cancel(MarketService $service): void
    {
        $this->need('marketplace', 'E');
        if ($this->attempt(fn () => $service->cancel($this->order(), $this->me(), $this->reason), 'Order cancelled. Stock went back to the store.')) {
            $this->reset('reason');
            $this->modal = null;
        }
    }

    public function render(MarketService $service)
    {
        $o = $this->order()->load('items', 'store.market', 'buyer', 'supplier', 'rfq', 'tasks.doer');
        $u = $this->me();
        $selling = in_array($u->role, ['seller', 'market_worker'], true);

        return view('livewire.market.order-show', ['o' => $o, 'next' => $service->nextFor($o, $u), 'selling' => $selling,
            'canCancel' => $u->allowed('marketplace', 'E') && (($selling && $u->role === 'seller' && in_array($o->status, ['placed', 'confirmed'], true)) || (! $selling && $o->status === 'placed')),
            'logs' => ActivityLog::where('subject_type', 'market_order')->where('subject_id', $o->id)->latest('created_at')->get()])->title($o->number);
    }
}
