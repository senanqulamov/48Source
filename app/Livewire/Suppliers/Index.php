<?php

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\SupplierLifecycleService;
use App\Support\Audit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Suppliers')]
class Index extends Component
{
    use Page, WithTable;

    public ?int $openId = null;

    public string $pane = 'details';

    public array $f = [];

    public string $to = '';

    public string $reason = '';

    public function mount(): void
    {
        abort_unless($this->me()->isSourcing(), 403);
    }

    protected function sortable(): array
    {
        return ['name', 'city', 'status', 'rating_tenths', 'created_at'];
    }

    public function show(int $id): void
    {
        $this->openId = Supplier::findOrFail($id)->id;
        $this->pane = 'details';
        $this->open('drawer');
    }

    public function create(): void
    {
        $this->need('suppliers', 'C');
        $this->f = ['id' => null, 'name' => '', 'category_id' => '', 'city' => '', 'contact_name' => '', 'email' => '', 'phone' => ''];
        $this->open('form');
    }

    public function edit(int $id): void
    {
        $this->need('suppliers', 'E');
        $s = Supplier::findOrFail($id);
        $this->f = ['id' => $s->id, 'name' => $s->name, 'category_id' => (string) $s->category_id, 'city' => (string) $s->city, 'contact_name' => (string) $s->contact_name, 'email' => (string) $s->email, 'phone' => (string) $s->phone];
        $this->open('form');
    }

    public function save(): void
    {
        $this->need('suppliers', $this->f['id'] ? 'E' : 'C');
        $this->validate(['f.name' => 'required|string|min:2|max:120', 'f.category_id' => 'nullable|exists:categories,id', 'f.city' => 'nullable|string|max:120', 'f.contact_name' => 'nullable|string|max:120', 'f.email' => 'nullable|email|max:190', 'f.phone' => 'nullable|string|max:40'],
            [], ['f.name' => 'company name', 'f.email' => 'email', 'f.category_id' => 'category']);
        $data = ['name' => trim($this->f['name']), 'category_id' => $this->f['category_id'] ?: null, 'city' => $this->f['city'] ?: null, 'contact_name' => $this->f['contact_name'] ?: null, 'email' => $this->f['email'] ? mb_strtolower($this->f['email']) : null, 'phone' => $this->f['phone'] ?: null];
        if ($this->f['id']) {
            $s = Supplier::findOrFail($this->f['id']);
            $s->update($data);
            Audit::log('Supplier', 'Edited supplier details', $s, supplierId: $s->id);
        } else {
            // A supplier added by someone who may approve starts active; otherwise it waits for approval.
            $active = $this->me()->allowed('suppliers', 'A');
            $s = Supplier::create($data + ['status' => $active ? 'active' : 'pending', 'approved_at' => $active ? now() : null, 'approved_by' => $active ? $this->me()->id : null]);
            Audit::log('Supplier', 'Added supplier'.($active ? '' : ' (waiting for approval)'), $s, supplierId: $s->id);
        }
        $this->close();
        $this->toast("{$s->name} saved.");
    }

    public function askStatus(int $id, string $to): void
    {
        $this->need('suppliers', 'A');
        $this->openId = $id;
        $this->to = $to;
        $this->reason = '';
        $this->open('status');
    }

    public function changeStatus(SupplierLifecycleService $life): void
    {
        $this->need('suppliers', 'A');
        $s = Supplier::findOrFail($this->openId);
        if ($this->attempt(fn () => $life->change($s, $this->to, $this->me(), $this->reason), "{$s->name} is now ".mb_strtolower(Supplier::STATUSES[$this->to] ?? $this->to).'.')) {
            $this->close();
        }
    }

    protected function exportData(): array
    {
        $this->need('suppliers');
        $rows = Supplier::with('category')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->orderBy('name')->get()
            ->map(fn (Supplier $s) => [$s->name, $s->statusLabel(), $s->category?->name, $s->city, $s->contact_name, $s->email, $s->phone, $s->rating()]);

        return ['suppliers', ['Supplier', 'Status', 'Category', 'City', 'Contact', 'Email', 'Phone', 'Rating'], $rows];
    }

    public function render()
    {
        $cats = $this->facet('cat', Category::pluck('id')->all());
        $rows = Supplier::with('category')->withCount(['quotes as quotes_count' => fn ($q) => $q->whereIn('status', ['submitted', 'won', 'lost']), 'purchaseOrders', 'invitations'])->when($cats, fn ($q) => $q->whereIn('category_id', $cats))
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->q}%")->orWhere('city', 'like', "%{$this->q}%")->orWhere('email', 'like', "%{$this->q}%")->orWhere('contact_name', 'like', "%{$this->q}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sortCol('name'), $this->sort === '' ? 'asc' : $this->sortDir())->paginate($this->perPage());
        $current = $this->openId ? Supplier::with('category', 'users')->withCount('invitations', 'purchaseOrders')->find($this->openId) : null;

        return view('livewire.suppliers.index', [
            'rows' => $rows, 'counts' => Supplier::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'categories' => Category::options(), 'current' => $current, 'catNames' => Category::whereIn('id', Supplier::whereNotNull('category_id')->select('category_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'logs' => $current && $this->modal === 'drawer' && $this->pane === 'history' ? ActivityLog::where('supplier_id', $current->id)->latest('created_at')->limit(80)->get() : collect(),
        ]);
    }
}
