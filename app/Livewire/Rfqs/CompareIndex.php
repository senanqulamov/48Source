<?php

namespace App\Livewire\Rfqs;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Rfq;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Compare quotations')]
class CompareIndex extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['number', 'status', 'quotes', 'deadline'];
    }

    public function render()
    {
        abort_unless($this->me()->isSourcing(), 403);

        $query = Rfq::visibleTo($this->me())
            ->whereIn('status', ['open', 'closed', 'awarded'])
            ->whereHas('liveQuotes')
            ->withCount('liveQuotes')
            ->with(['liveQuotes:id,rfq_id,total_cents,currency,status']);
        $all = (clone $query)->count();

        if ($this->q !== '') {
            $like = '%'.trim($this->q).'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like));
        }
        if ($this->status !== '') $query->where('status', $this->status);

        match ($this->sortCol('deadline')) {
            'number' => $query->orderBy('number', $this->sortDir()),
            'status' => $query->orderBy('status', $this->sortDir())->orderByDesc('closed_at'),
            'quotes' => $query->orderBy('live_quotes_count', $this->sortDir()),
            default => $query->orderBy('deadline_at', $this->sortDir()),
        };

        return view('livewire.rfqs.compare-index', ['rows' => $query->paginate($this->perPage()), 'all' => $all]);
    }
}
