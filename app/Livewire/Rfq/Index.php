<?php

namespace App\Livewire\Rfq;

use App\Enums\TableHeaders;
use App\Livewire\Traits\Alert;
use App\Livewire\Traits\WithLogging;
use App\Models\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use Alert, WithLogging, WithPagination;

    public string $status = 'all';

    public ?string $search = null;

    public string $typeFilter = 'all';

    public string $buyerFilter = 'all';

    public $quantity = 10;

    public array $sort = [
        'column' => 'created_at',
        'direction' => 'desc',
    ];

    public array $headers = [
        ['index' => 'id', 'label' => '#'],
        ['index' => 'title', 'label' => 'Title'],
        ['index' => 'deadline', 'label' => 'Deadline'],
        ['index' => 'request_type', 'label' => 'Type'],
        ['index' => 'status', 'label' => 'Status'],
        ['index' => 'items_count', 'label' => 'Items'],
        ['index' => 'quotes_count', 'label' => 'Quotes'],
        ['index' => 'created_at', 'label' => 'Created'],
        ['index' => 'action', 'label' => 'Actions', 'sortable' => false],
    ];

    protected $queryString = [
        'status' => ['except' => 'all'],
        'search' => ['except' => ''],
        'quantity' => ['except' => 10],
        'sort' => ['except' => ['column' => 'created_at', 'direction' => 'desc']],
    ];

    public function mount(): void
    {
        $this->headers = TableHeaders::make($this->headers);
        $this->logPageView('RFQ Index');
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingQuantity(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.rfq.index');
    }

    public function changeSort(string $column): void
    {
        $allowed = ['id', 'title', 'deadline', 'request_type', 'status', 'created_at'];
        if (!in_array($column, $allowed, true)) {
            return;
        }

        if (($this->sort['column'] ?? '') !== $column) {
            $this->sort = ['column' => $column, 'direction' => 'asc'];
        } elseif (($this->sort['direction'] ?? '') === 'asc') {
            $this->sort = ['column' => $column, 'direction' => 'desc'];
        } else {
            $this->sort = ['column' => 'created_at', 'direction' => 'desc'];
        }

        $this->resetPage();
    }

    public function clearTableFilters(): void
    {
        $this->search = null;
        $this->typeFilter = 'all';
        $this->buyerFilter = 'all';
        $this->status = 'all';
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedBuyerFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function statusCounts(): array
    {
        $base = Request::query();
        
        return $base->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    #[Computed]
    public function buyers(): array
    {
        return \App\Models\User::query()->whereIn('id', Request::query()->select('buyer_id'))->orderBy('name')->pluck('name', 'id')->toArray();
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $query = Request::query()
            
            ->with(['buyer:id,name'])
            ->withCount(['items', 'quotes', 'supplierInvitations']);

        if ($this->status !== 'all') { $query->where('status', $this->status); }

        if ($this->typeFilter !== 'all' && in_array($this->typeFilter, ['public', 'internal'], true)) {
            $query->where('request_type', $this->typeFilter);
        }

        if ($this->buyerFilter !== 'all' && ctype_digit($this->buyerFilter)) {
            $query->where('buyer_id', (int) $this->buyerFilter);
        }

        if (filled(trim((string) $this->search))) {
            $search = trim((string) $this->search);
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('buyer', fn (Builder $buyer) => $buyer->where('name', 'like', '%'.$search.'%'));
                if (ctype_digit(ltrim($search, '#'))) {
                    $builder->orWhere('id', (int) ltrim($search, '#'));
                }
            });
        }

        $column = $this->sort['column'] ?? 'created_at';
        $allowed = ['id', 'title', 'deadline', 'request_type', 'status', 'created_at'];
        if (!in_array($column, $allowed, true)) {
            $column = 'created_at';
        }
        $direction = ($this->sort['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $pageSize = in_array((int) $this->quantity, [10, 25, 50], true) ? (int) $this->quantity : 10;

        return $query->orderBy($column, $direction)
            ->paginate($pageSize)
            ->withQueryString();
    }
}
