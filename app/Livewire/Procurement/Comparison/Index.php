<?php

namespace App\Livewire\Procurement\Comparison;

use App\Models\Request;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function render(): View
    {
        $actor = auth()->user();
        abort_unless($actor && ($actor->isAdmin() || $actor->isBuyer()) && $actor->can('view_rfqs'), 403);

        $requests = Request::query()
            ->when(! $actor->isAdmin(), fn ($query) => $query->where('buyer_id', $actor->id))
            ->whereHas('quotes', fn ($query) => $query->whereIn('status', ['submitted', 'accepted']))
            ->with(['buyer:id,name'])
            ->withCount(['quotes' => fn ($query) => $query->whereIn('status', ['submitted', 'accepted'])])
            ->when($this->status !== 'all',
                fn ($query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(function ($where) {
                $term = '%'.trim($this->search).'%';
                $where->where('title', 'like', $term);
                if (ctype_digit(ltrim(trim($this->search), '#'))) {
                    $where->orWhere('id', (int) ltrim(trim($this->search), '#'));
                }
            }))
            ->latest()
            ->paginate(10);

        return view('livewire.procurement.comparison.index', compact('requests'))
            ->layout('layouts.app');
    }
}
