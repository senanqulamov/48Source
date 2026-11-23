<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Market;
use App\Models\Offer;
use App\Models\Product;
use App\Services\MarketService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Marketplace')]
class Shop extends Component
{
    use Page, WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $cat = '';

    #[Url(except: '')]
    public string $market = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public ?int $openId = null;

    /** Quantity typed per offer id. */
    public array $qty = [];

    public function updated($name): void
    {
        if (in_array($name, ['q', 'cat', 'market', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clear(): void
    {
        $this->reset('q', 'cat', 'market');
        $this->resetPage();
    }

    public function show(int $productId): void
    {
        $this->openId = $productId;
        $this->qty = [];
        $this->open('offers');
    }

    public function add(MarketService $service, int $offerId): void
    {
        $this->need('marketplace', 'C');
        $offer = Offer::live()->with('product', 'store')->findOrFail($offerId);
        $typed = trim((string) ($this->qty[$offerId] ?? '')) ?: \App\Support\Money::qtyInput($offer->moq_milli);
        $this->attempt(fn () => $service->add($this->me(), $offer, $typed), "{$offer->product->name} from {$offer->store->name} is in your basket.");
    }

    public function render()
    {
        $live = fn ($q) => $q->live()->when($this->market !== '', fn ($o) => $o->whereHas('store', fn ($s) => $s->where('market_id', (int) $this->market)));
        $rows = Product::where('status', 'active')->whereHas('offers', $live)->with(['category', 'offers' => fn ($q) => $live($q)->with('store.market')])
            ->when($this->cat !== '', fn ($q) => $q->where('category_id', (int) $this->cat))
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->q}%")->orWhere('sku', 'like', "%{$this->q}%")->orWhereHas('offers.store', fn ($s) => $s->where('name', 'like', "%{$this->q}%"))))
            ->withMin(['offers as min_price' => $live], 'price_cents')->withSum(['offers as stock' => $live], 'stock_milli')
            ->orderBy(match ($this->sort) { 'low' => 'min_price', 'high' => 'min_price', 'stock' => 'stock', default => 'name' }, in_array($this->sort, ['high', 'stock'], true) ? 'desc' : 'asc')->paginate(12);
        $u = $this->me();

        return view('livewire.market.shop', [
            'rows' => $rows, 'canBuy' => $u->canShop(), 'basket' => $u->canShop() ? CartItem::where('user_id', $u->id)->count() : 0,
            'cats' => Category::whereIn('id', Product::where('status', 'active')->whereNotNull('category_id')->select('category_id'))->orderBy('name')->pluck('name', 'id'),
            'markets' => Market::where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'current' => $this->modal === 'offers' && $this->openId ? Product::with(['category', 'offers' => fn ($q) => $q->live()->with('store.market')->orderBy('price_cents')])->find($this->openId) : null,
            'inBasket' => $u->canShop() ? CartItem::where('user_id', $u->id)->pluck('quantity_milli', 'offer_id') : collect(),
        ]);
    }
}
