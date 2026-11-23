<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Models\CartItem;
use App\Services\MarketService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Basket')]
class Basket extends Component
{
    use Page;

    public array $qty = [];

    public string $deliverTo = '';

    public string $note = '';

    public function mount(): void
    {
        abort_unless($this->me()->canShop(), 403);
        foreach (CartItem::where('user_id', $this->me()->id)->get() as $c) {
            $this->qty[$c->id] = Money::qtyInput($c->quantity_milli);
        }
    }

    public function setQty(MarketService $service, int $id): void
    {
        $line = CartItem::where('user_id', $this->me()->id)->with('offer.product', 'offer.store', 'rfqItem')->findOrFail($id);
        if (! $this->attempt(fn () => $service->add($this->me(), $line->offer, (string) ($this->qty[$id] ?? ''), $line->rfqItem))) {
            $this->qty[$id] = Money::qtyInput($line->quantity_milli);
        }
    }

    public function remove(MarketService $service, int $id): void
    {
        $service->remove($this->me(), $id);
        unset($this->qty[$id]);
    }

    public function checkout(MarketService $service)
    {
        $this->need('marketplace', 'C');
        $orders = null;
        if ($this->attempt(function () use ($service, &$orders) {
            $orders = $service->checkout($this->me(), $this->deliverTo, $this->note);
        })) {
            session()->flash('toast', $orders->count() === 1 ? "Order {$orders->first()->number} placed." : $orders->count() . ' orders placed, one for each store.');

            return $this->redirectRoute('market.orders.index', navigate: true);
        }
    }

    public function render(MarketService $service)
    {
        $groups = $service->basket($this->me());

        return view('livewire.market.basket', ['groups' => $groups, 'total' => (int) $groups->sum('total'), 'lines' => (int) $groups->sum(fn ($g) => $g['lines']->count())]);
    }
}
