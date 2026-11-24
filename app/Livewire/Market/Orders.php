<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\MarketOrder;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Marketplace orders')]
class Orders extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['number', 'status', 'total_cents', 'created_at'];
    }

    private function query()
    {
        return MarketOrder::visibleTo($this->me())->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhereHas('store', fn ($s) => $s->where('name', 'like', "%{$this->q}%"))->orWhereHas('items', fn ($i) => $i->where('name', 'like', "%{$this->q}%"))));
    }

    protected function exportData(): array
    {
        $rows = $this->picked($this->query(), 'number')->with('store', 'buyer', 'supplier')->orderByDesc('id')->get()->map(fn (MarketOrder $o) => [$o->number, $o->store->name, $o->supplier?->name ?? $o->buyer->name, $o->statusLabel(), Money::input($o->total_cents), $o->currency, $o->created_at->format('Y-m-d'), $o->delivered_at?->format('Y-m-d')]);

        return ['marketplace-orders', ['Order', 'Store', 'Buyer', 'Status', 'Amount', 'Currency', 'Placed', 'Delivered'], $rows];
    }

    public function render()
    {
        $rows = $this->query()->with('store.market', 'buyer', 'supplier', 'rfq')->withCount('items')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());

        return view('livewire.market.orders', ['rows' => $rows, 'counts' => $this->query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'all' => MarketOrder::visibleTo($this->me())->count(), 'selling' => in_array($this->me()->role, ['seller', 'market_worker'], true)]);
    }
}
