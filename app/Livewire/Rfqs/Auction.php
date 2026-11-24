<?php

namespace App\Livewire\Rfqs;

use App\Livewire\Concerns\Page;
use App\Models\Rfq;
use App\Services\AuctionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Reverse auction')]
class Auction extends Component
{
    use Page;

    public string $number;

    public string $amount = '';

    public function mount(string $number): void
    {
        $this->number = $number;
        abort_unless($this->rfq()->auction, 404);
    }

    protected function rfq(): Rfq
    {
        return Rfq::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function bid(AuctionService $service): void
    {
        $this->need('quotes', 'E');
        if ($this->attempt(fn () => $service->bid($this->rfq()->auction, $this->me(), $this->amount), 'Your new total is recorded.')) {
            $this->amount = '';
        }
    }

    public function endNow(AuctionService $service): void
    {
        $this->need('quotes', 'A');
        abort_unless($this->me()->isSourcing(), 403);
        $this->attempt(fn () => $service->end($this->rfq()->auction), 'Auction ended. Final totals are on the quotations.');
    }

    public function render(AuctionService $service)
    {
        $rfq = $this->rfq();
        $a = $rfq->auction;
        if ($a->status === 'live' && $a->ends_at->isPast()) {
            $a = $service->end($a);
        }
        $u = $this->me();
        $standing = $service->standing($a);
        $rank = $u->supplier_id ? array_search($u->supplier_id, array_keys($standing), true) : false;

        return view('livewire.rfqs.auction', ['rfq' => $rfq, 'a' => $a, 'standing' => $standing, 'sourcing' => $u->isSourcing(), 'rank' => $rank === false ? null : $rank + 1, 'own' => $u->supplier_id ? ($standing[$u->supplier_id] ?? null) : null, 'updatedAt' => now()->timestamp,
            'ceiling' => $u->supplier_id && $a->isLive() ? $service->ceilingFor($a, $u->supplier_id) : null, 'bids' => $u->isSourcing() ? $a->bids()->with('supplier')->latest('id')->limit(30)->get() : $a->bids()->where('supplier_id', $u->supplier_id)->latest('id')->limit(30)->get()])->title('Auction ' . $rfq->number);
    }
}
