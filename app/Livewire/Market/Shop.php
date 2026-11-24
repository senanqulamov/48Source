<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Market;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Store;
use App\Services\MarketService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The marketplace as in the accepted design. One component, two pages:
 * "shop" has the filter panel and the By product / All offers views,
 * "products" is the catalogue with the summary band, category chips and the Showcase / Table switch.
 * Both open the same "Compare offers" drawer.
 */
#[Layout('components.layouts.app')]
#[Title('Marketplace')]
class Shop extends Component
{
    use Page, WithPagination;

    public string $page = 'shop';

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $cat = '';

    #[Url(except: 'rel')]
    public string $sort = 'rel';

    /** shop: product or offers. products: showcase or table. */
    #[Url(except: '')]
    public string $view = '';

    /** @var array<int, string> store ids */
    #[Url(except: [])]
    public array $stores = [];

    /** @var array<int, string> market ids */
    #[Url(except: [])]
    public array $markets = [];

    /** Kept for old links: one market id. */
    #[Url(except: '')]
    public string $market = '';

    /** Delivery within this many days, or empty for any. */
    #[Url(except: '')]
    public string $lead = '';

    #[Url(except: false)]
    public bool $stock = false;

    /** products page: '', in, low or out */
    #[Url(except: '')]
    public string $avail = '';

    public ?int $openId = null;

    /** Quantity typed per offer id. */
    public array $qty = [];

    public function mount(string $page = 'shop'): void
    {
        $this->page = $page === 'products' ? 'products' : 'shop';
        $this->need('marketplace');
        if ($this->market !== '') {
            $this->markets = [$this->market];
            $this->market = '';
        }
        if ($this->view === '') {
            $this->view = $this->page === 'products' ? 'showcase' : 'product';
        }
    }

    public function updated($name): void
    {
        if ($name !== 'openId' && ! str_starts_with((string) $name, 'qty')) {
            $this->resetPage();
        }
    }

    public function setCat(string $id): void
    {
        $this->cat = $id;
        $this->resetPage();
    }

    public function setLead(string $days): void
    {
        $this->lead = in_array($days, ['2', '5', '7'], true) ? $days : '';
        $this->resetPage();
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['product', 'offers', 'showcase', 'table'], true) ? $view : $this->view;
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset('q', 'cat', 'stores', 'markets', 'lead', 'stock', 'avail');
        $this->resetPage();
    }

    /** Takes one filter off, from the row of filters in use above the results. */
    public function drop(string $kind, string $id = ''): void
    {
        match ($kind) {
            'q' => $this->q = '',
            'cat' => $this->cat = '',
            'lead' => $this->lead = '',
            'avail' => $this->avail = '',
            'store' => $this->stores = array_values(array_filter($this->stores, fn ($v) => (string) $v !== $id)),
            'market' => $this->markets = array_values(array_filter($this->markets, fn ($v) => (string) $v !== $id)),
            default => null,
        };
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

    /** The offers a buyer can order, narrowed by the filters. */
    private function offers($q, bool $withStockRule = true)
    {
        $stores = array_filter(array_map('intval', $this->stores));
        $markets = array_filter(array_map('intval', $this->markets));

        return $q->where('offers.status', 'active')
            ->whereHas('store', fn ($s) => $s->where('status', 'active')->when($markets, fn ($x) => $x->whereIn('market_id', $markets)))
            ->when($withStockRule && ($this->page === 'shop' || $this->stock), fn ($x) => $x->where('stock_milli', '>', 0))
            ->when($stores, fn ($x) => $x->whereIn('store_id', $stores))
            ->when($this->lead !== '', fn ($x) => $x->where('lead_days', '<=', (int) $this->lead));
    }

    private function products()
    {
        $term = trim($this->q);

        return Product::query()->where('products.status', 'active')->whereHas('offers', fn ($o) => $this->offers($o))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhereHas('offers.store', fn ($s) => $s->where('name', 'like', "%{$term}%"))));
    }

    public function render()
    {
        $u = $this->me();
        $live = fn ($o) => $this->offers($o);
        $base = $this->products();

        // Category list with the number of products in each, before the category filter is applied.
        $counts = (clone $base)->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        // Biggest categories first, so the short list shows the ones people most likely want.
        $catNames = Category::whereIn('id', $counts->keys()->filter())->orderBy('name')->pluck('name', 'id')
            ->sortByDesc(fn ($name, $id) => (int) ($counts[$id] ?? 0));

        $filtered = (clone $base)->when($this->cat !== '', fn ($q) => $q->where('category_id', (int) $this->cat));
        $withFigures = fn ($q) => $q->with(['category', 'offers' => fn ($o) => $live($o)->with('store.market')->orderBy('price_cents')])
            ->withMin(['offers as min_price' => $live], 'price_cents')->withMin(['offers as min_lead' => $live], 'lead_days')
            ->withSum(['offers as stock_sum' => $live], 'stock_milli')->withCount(['offers as sellers' => $live]);

        $rows = null;
        $offerRows = null;
        if ($this->page === 'shop' && $this->view === 'offers') {
            $term = trim($this->q);
            $offerRows = $this->offers(Offer::query())->whereHas('product', fn ($p) => $p->where('status', 'active')
                    ->when($this->cat !== '', fn ($x) => $x->where('category_id', (int) $this->cat))
                    ->when($term !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"))))
                ->with('product.category', 'store.market')
                ->orderBy($this->sort === 'lead' ? 'lead_days' : 'price_cents', $this->sort === 'high' ? 'desc' : 'asc')->orderBy('id')->paginate(15);
        } else {
            // Availability is judged on the total stock of a product's active offers.
            $sum = "(select coalesce(sum(o2.stock_milli), 0) from offers o2 where o2.product_id = products.id and o2.status = 'active')";
            $query = $withFigures($filtered)
                ->when($this->page === 'products' && $this->avail === 'in', fn ($q) => $q->whereRaw("$sum > 10000"))
                ->when($this->page === 'products' && $this->avail === 'low', fn ($q) => $q->whereRaw("$sum > 0")->whereRaw("$sum <= 10000"))
                ->when($this->page === 'products' && $this->avail === 'out', fn ($q) => $q->whereRaw("$sum <= 0"));
            [$col, $dir] = match ($this->sort) {
                'low' => ['min_price', 'asc'], 'high' => ['min_price', 'desc'], 'lead' => ['min_lead', 'asc'], 'name' => ['name', 'asc'],
                default => $this->page === 'products' ? ['name', 'asc'] : ['sellers', 'desc'],
            };
            $rows = $query->orderBy($col, $dir)->orderBy('name')->paginate($this->page === 'products' && $this->view === 'table' ? 15 : 12);
        }

        $strip = [];
        if ($this->page === 'products') {
            $all = Product::where('status', 'active')->whereHas('offers', fn ($o) => $o->where('status', 'active'));
            $inStock = (clone $all)->whereHas('offers', fn ($o) => $o->where('status', 'active')->where('stock_milli', '>', 0))->count();
            $total = (clone $all)->count();
            $value = (int) Offer::where('status', 'active')->get(['price_cents', 'stock_milli'])->sum(fn ($o) => intdiv($o->price_cents * $o->stock_milli, 1000));
            $strip = [
                ['l' => 'Products', 'v' => $total, 's' => $catNames->count().' categories'],
                ['l' => 'In stock', 'v' => $inStock, 's' => 'can be ordered now'],
                ['l' => 'Out of stock', 'v' => $total - $inStock, 's' => 'not orderable now', 'c' => $total - $inStock ? 'warn' : ''],
                ['l' => 'Stock value', 'v' => \App\Support\Money::format($value, 'USD', false), 's' => 'at list prices'],
            ];
        }

        // Sellers with the most offers first. The panel shows a few and finds the rest by typing.
        $storeList = collect();
        $storeCounts = collect();
        if ($this->page === 'shop') {
            $storeCounts = Offer::where('status', 'active')->selectRaw('store_id, count(*) as n')->groupBy('store_id')->pluck('n', 'store_id');
            $storeList = Store::where('status', 'active')->whereIn('id', $storeCounts->keys())->orderBy('name')->pluck('name', 'id')
                ->sortByDesc(fn ($name, $id) => (int) ($storeCounts[$id] ?? 0))->take(300);
        }
        $marketList = Market::where('status', 'active')->orderBy('name')->pluck('name', 'id');

        // The filters in use, shown above the results so nobody has to look for them in the panel.
        $used = [];
        if (trim($this->q) !== '') {
            $used[] = ['q', '', 'Search', trim($this->q)];
        }
        if ($this->cat !== '') {
            $used[] = ['cat', '', 'Category', $catNames[(int) $this->cat] ?? Category::whereKey((int) $this->cat)->value('name') ?? 'General'];
        }
        if ($this->stores) {
            foreach (Store::whereIn('id', array_map('intval', $this->stores))->orderBy('name')->pluck('name', 'id') as $id => $name) {
                $used[] = ['store', (string) $id, 'Seller', $name];
            }
        }
        if ($this->page === 'shop') {
            foreach ($marketList->only(array_map('intval', $this->markets)) as $id => $name) {
                $used[] = ['market', (string) $id, 'Market', $name];
            }
            if ($this->lead !== '') {
                $used[] = ['lead', '', 'Delivery within', $this->lead.' d'];
            }
        }

        return view('livewire.market.shop', [
            'rows' => $rows, 'offerRows' => $offerRows, 'strip' => $strip,
            'canBuy' => $u->canShop(), 'basket' => $u->canShop() ? CartItem::where('user_id', $u->id)->count() : 0,
            'counts' => $counts, 'catNames' => $catNames, 'total' => (int) $counts->sum(),
            'storeList' => $storeList, 'storeCounts' => $storeCounts,
            'marketList' => $marketList, 'used' => $used,
            'current' => $this->modal === 'offers' && $this->openId ? Product::with(['category', 'offers' => fn ($q) => $q->live()->with('store.market')->orderBy('price_cents')])->find($this->openId) : null,
            'inBasket' => $u->canShop() ? CartItem::where('user_id', $u->id)->pluck('quantity_milli', 'offer_id') : collect(),
        ])->title($this->page === 'products' ? 'Products' : 'Marketplace');
    }
}
