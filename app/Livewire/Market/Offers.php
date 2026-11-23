<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Category;
use App\Models\Offer;
use App\Services\SellerService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('components.layouts.app')]
#[Title('My products')]
class Offers extends Component
{
    use Page, WithFileUploads, WithTable;

    public ?int $editId = null;

    public array $o = ['sku' => '', 'name' => '', 'unit' => 'pcs', 'category' => '', 'price' => '', 'moq' => '1', 'stock' => '', 'lead_days' => '1'];

    public $file = null;

    public array $report = [];

    public function mount(): void
    {
        abort_unless($this->me()->role === 'seller' && $this->me()->store_id, 403, 'Your account is not linked to a store yet. Ask an administrator.');
    }

    protected function sortable(): array
    {
        return ['price_cents', 'stock_milli', 'lead_days', 'status', 'updated_at'];
    }

    public function create(): void
    {
        $this->need('catalog', 'C');
        $this->reset('o', 'editId');
        $this->open('form');
    }

    public function edit(int $id): void
    {
        $this->need('catalog', 'E');
        $x = Offer::where('store_id', $this->me()->store_id)->with('product')->findOrFail($id);
        $this->editId = $id;
        $this->o = ['sku' => $x->product->sku, 'name' => $x->product->name, 'unit' => $x->product->unit, 'category' => (string) $x->product->category_id, 'price' => Money::input($x->price_cents), 'moq' => Money::qtyInput($x->moq_milli), 'stock' => Money::qtyInput($x->stock_milli), 'lead_days' => (string) $x->lead_days];
        $this->open('form');
    }

    public function save(SellerService $service): void
    {
        $this->need('catalog', $this->editId ? 'E' : 'C');
        $existing = $this->editId ? Offer::where('store_id', $this->me()->store_id)->with('product')->findOrFail($this->editId) : null;
        if ($this->attempt(fn () => $service->saveOffer($this->me()->store, $this->o, $existing), 'Offer saved.')) {
            $this->modal = null;
        }
    }

    public function toggle(SellerService $service, int $id): void
    {
        $this->need('catalog', 'E');
        $this->attempt(fn () => $service->toggle(Offer::findOrFail($id), $this->me()->store));
    }

    public function import(SellerService $service): void
    {
        $this->need('catalog', 'C');
        $this->validate(['file' => 'required|file|mimes:xlsx,csv,txt|max:2048'], ['file.required' => 'Choose a file first.', 'file.mimes' => 'Upload an Excel (.xlsx) or CSV file.', 'file.max' => 'The file is larger than 2 MB.']);
        $this->attempt(function () use ($service) {
            $rows = Excel::toArray(new \stdClass, $this->file)[0] ?? [];
            $this->report = $service->import($this->me()->store, $rows);
            $this->toast("{$this->report['saved']} offer(s) saved" . ($this->report['errors'] ? ', ' . count($this->report['errors']) . ' row(s) skipped.' : '.'), $this->report['errors'] ? 'alert' : 'check');
        });
        $this->reset('file');
    }

    public function template()
    {
        return response()->streamDownload(function () {
            echo "sku,name,unit,category,price,moq,stock,lead_days\nRB-16,Rebar B500B 16 mm,t,Steel & metals,698.00,1,120,3\n";
        }, 'price-list-template.csv', ['Content-Type' => 'text/csv']);
    }

    private function query()
    {
        return Offer::where('store_id', $this->me()->store_id)->when($this->q !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->q}%")->orWhere('sku', 'like', "%{$this->q}%")));
    }

    protected function exportData(): array
    {
        $rows = $this->query()->with('product.category')->get()->map(fn (Offer $x) => [$x->product->sku, $x->product->name, $x->product->unit, $x->product->category?->name, Money::input($x->price_cents), Money::qtyInput($x->moq_milli), Money::qtyInput($x->stock_milli), $x->lead_days]);

        return ['price-list', ['sku', 'name', 'unit', 'category', 'price', 'moq', 'stock', 'lead_days'], $rows];
    }

    public function render()
    {
        $rows = $this->query()->with('product.category')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->orderBy($this->sortCol('updated_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());

        return view('livewire.market.offers', ['rows' => $rows, 'store' => $this->me()->store->load('market'), 'counts' => $this->query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'all' => Offer::where('store_id', $this->me()->store_id)->count(), 'categories' => Category::options()]);
    }
}
