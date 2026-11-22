<?php

namespace App\Livewire\Buyer\Rfq;

use App\Enums\TableHeaders;
use App\Models\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $quantity = 10;

    public ?string $search = null;

    public string $typeFilter = 'all';

    public string $buyerFilter = 'all';

    public ?string $statusFilter = null;

    public array $sort = [
        'column' => 'created_at',
        'direction' => 'desc',
    ];

    public array $headers = [
        ['index' => 'id', 'label' => '#'],
        ['index' => 'title', 'label' => 'Title'],
        ['index' => 'deadline', 'label' => 'Deadline'],
        ['index' => 'status', 'label' => 'Status'],
        ['index' => 'items_count', 'label' => 'Items'],
        ['index' => 'quotes_count', 'label' => 'Quotes'],
        ['index' => 'created_at', 'label' => 'Created'],
        ['index' => 'action', 'sortable' => false],
    ];

    public function mount(): void
    {
        // Translate headers based on current locale
        $this->headers = TableHeaders::make($this->headers);
    }

    public function render(): View
    {
        return view('livewire.buyer.rfq.index');
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
        $this->statusFilter = null;
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
        $base->where('buyer_id', auth()->id());
        return $base->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    #[Computed]
    public function buyers(): array
    {
        return [];
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $query = Request::query()
            ->where('buyer_id', auth()->id())
            ->with(['buyer:id,name'])
            ->withCount(['items', 'quotes', 'supplierInvitations']);

        if ($this->statusFilter !== null && $this->statusFilter !== 'all') { $query->where('status', $this->statusFilter); }

        if ($this->typeFilter !== 'all' && in_array($this->typeFilter, ['public', 'internal'], true)) {
            $query->where('request_type', $this->typeFilter);
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
