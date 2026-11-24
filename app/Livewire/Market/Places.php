<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Models\Market;
use App\Models\MarketOrder;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Markets as cards: what is sold in each, how many stores, open orders and workers. */
#[Layout('components.layouts.app')]
#[Title('Markets')]
class Places extends Component
{
    use Page;

    public function mount(): void
    {
        abort_unless($this->me()->allowed('marketplace') || $this->me()->allowed('catalog'), 403);
    }

    public function render()
    {
        $u = $this->me();
        $manage = $u->allowed('catalog', 'A');
        $markets = Market::query()->when(! $manage, fn ($q) => $q->where('status', 'active'))->orderBy('name')->get();
        $stores = Store::whereIn('market_id', $markets->pluck('id'))->where('status', 'active')->get(['id', 'market_id', 'name']);
        $byMarket = $stores->groupBy('market_id');
        $storeMarket = $stores->pluck('market_id', 'id');
        $count = fn ($rows) => $rows->groupBy(fn ($r) => $storeMarket[$r->store_id] ?? 0)->map->count();

        $offers = Offer::where('status', 'active')->whereIn('store_id', $stores->pluck('id'))->with('product:id,sku,name,category_id', 'product.category:id,name')->get(['id', 'store_id', 'product_id', 'price_cents', 'currency']);
        $products = $offers->groupBy(fn ($o) => $storeMarket[$o->store_id] ?? 0)->map(fn ($g) => $g->unique('product_id'));
        $open = $count(MarketOrder::visibleTo($u)->whereIn('store_id', $stores->pluck('id'))->whereIn('status', ['placed', 'confirmed', 'ready'])->get(['id', 'store_id']));
        $workers = $count(User::whereIn('store_id', $stores->pluck('id'))->where('role', 'market_worker')->where('status', 'active')->get(['id', 'store_id']));

        return view('livewire.market.places', [
            'markets' => $markets, 'byMarket' => $byMarket, 'products' => $products, 'open' => $open, 'workers' => $workers, 'manage' => $manage, 'mine' => $u->store_id ? ($storeMarket[$u->store_id] ?? null) : null,
            'strip' => [
                ['l' => 'Markets', 'v' => $markets->count(), 's' => $markets->where('status', 'active')->count().' active'],
                ['l' => 'Products listed', 'v' => $products->sum(fn ($g) => $g->count()), 's' => 'across all markets'],
                ['l' => 'Open orders', 'v' => (int) $open->sum(), 's' => 'being prepared or shipped', 'href' => $u->allowed('marketplace') ? route('market.orders.index') : null],
                ['l' => 'Market workers', 'v' => (int) $workers->sum(), 's' => 'assigned to stores'],
            ],
        ]);
    }
}
