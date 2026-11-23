<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Services\MarketService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Sourcing')]
class Sourcing extends Component
{
    use Page;

    public string $number = '';

    public array $qty = [];

    public function mount(?string $number = null): void
    {
        abort_unless($this->me()->canShop(), 403);
        $this->number = $number && $this->requests()->where('number', $number)->exists() ? $number : '';
    }

    /** Requests this user works on: a supplier's invitations that are open or won, a buyer's own requests. */
    private function requests()
    {
        $u = $this->me();
        $q = Rfq::visibleTo($u)->whereIn('status', ['open', 'closed', 'awarded']);

        return $u->role === 'supplier' ? $q->whereHas('invitations', fn ($i) => $i->where('supplier_id', $u->supplier_id)->where('status', '!=', 'declined')) : $q;
    }

    public function updatedNumber(): void
    {
        $this->qty = [];
    }

    public function add(MarketService $service, int $itemId, int $offerId): void
    {
        $this->need('marketplace', 'C');
        $rfq = $this->requests()->where('number', $this->number)->firstOrFail();
        $item = RfqItem::where('rfq_id', $rfq->id)->findOrFail($itemId);
        $offer = Offer::live()->with('product', 'store')->findOrFail($offerId);
        $typed = trim((string) ($this->qty[$itemId][$offerId] ?? '')) ?: Money::qtyInput(max($item->quantity_milli, $offer->moq_milli));
        $this->attempt(fn () => $service->add($this->me(), $offer, $typed, $item), "Added from {$offer->store->name}.");
    }

    public function cheapest(MarketService $service): void
    {
        $this->need('marketplace', 'C');
        $rfq = $this->requests()->where('number', $this->number)->with('items')->firstOrFail();
        $this->attempt(function () use ($service, $rfq) {
            $n = $service->addCheapestMix($this->me(), $rfq);
            $this->toast("Cheapest offer added for {$n} of {$rfq->items->count()} items. Check the basket before ordering.");
        });
    }

    public function render(MarketService $service)
    {
        $rfq = $this->number !== '' ? $this->requests()->where('number', $this->number)->with('items')->first() : null;
        $plan = $rfq ? $service->sourcingPlan($rfq) : collect();
        $cart = CartItem::where('user_id', $this->me()->id)->with('offer.store')->get();

        return view('livewire.market.sourcing', ['list' => $this->requests()->latest('id')->limit(50)->get(['id', 'number', 'title', 'status']), 'rfq' => $rfq, 'plan' => $plan,
            'cart' => $cart->whereNotNull('rfq_item_id')->groupBy('rfq_item_id'), 'basket' => $cart->count(),
            'mix' => (int) $plan->sum(fn ($r) => $r['best'] ? Money::lineTotal($r['best']->price_cents, $r['qty']) : 0), 'covered' => $plan->filter(fn ($r) => $r['best'])->count(), 'stores' => $plan->pluck('best.store_id')->filter()->unique()->count()]);
    }
}
