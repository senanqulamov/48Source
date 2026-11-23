<?php

namespace App\Livewire\Rfqs;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Rfq;
use App\Services\RfqService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('RFQs')]
class Index extends Component
{
    use Page, WithTable;

    public function mount(): void
    {
        abort_unless($this->me()->isSourcing(), 403);
    }

    protected function sortable(): array
    {
        return ['number', 'title', 'type', 'status', 'deadline_at', 'created_at', 'live_quotes_count', 'items_count'];
    }

    public function closeSelected(RfqService $service): void
    {
        $this->need('rfqs', 'E');
        $done = 0;
        foreach (Rfq::visibleTo($this->me())->whereIn('number', $this->selected)->where('status', 'open')->get() as $rfq) {
            $service->close($rfq);
            $done++;
        }
        $this->selected = [];
        $this->toast($done ? "{$done} request(s) closed to quotations." : 'None of the selected requests is open.', $done ? 'check' : 'alert');
    }

    private function query()
    {
        $types = $this->facet('type', array_keys(Rfq::TYPES));
        $cats = $this->facet('cat', \App\Models\Category::pluck('id')->all());
        $buyers = $this->facet('buyer', \App\Models\User::pluck('id')->all());

        return Rfq::visibleTo($this->me())
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhere('title', 'like', "%{$this->q}%")
                ->orWhereHas('buyer', fn ($b) => $b->where('name', 'like', "%{$this->q}%"))->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$this->q}%"))))
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->when($cats, fn ($q) => $q->whereIn('category_id', $cats))
            ->when($buyers, fn ($q) => $q->whereIn('buyer_id', $buyers));
    }

    protected function exportData(): array
    {
        $this->need('rfqs');
        $rows = $this->query()->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->with('category', 'buyer')->withCount('liveQuotes', 'items')->orderByDesc('id')->get()
            ->map(fn (Rfq $r) => [$r->number, $r->title, $r->buyer->name, Rfq::TYPES[$r->type] ?? $r->type, $r->category?->name, $r->statusLabel(), $r->deadline_at?->format('Y-m-d H:i'), $r->items_count, $r->live_quotes_count, $r->created_at->format('Y-m-d')]);

        return ['rfqs', ['Reference', 'Title', 'Buyer', 'Type', 'Category', 'Status', 'Deadline', 'Items', 'Quotations', 'Created'], $rows];
    }

    public function render()
    {
        $rows = $this->query()->with('category', 'buyer')->withCount('liveQuotes', 'invitations', 'items')
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')
            ->paginate($this->perPage());
        $mine = Rfq::visibleTo($this->me());

        return view('livewire.rfqs.index', [
            'rows' => $rows,
            'all' => (clone $mine)->count(),
            'counts' => $this->query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'cats' => \App\Models\Category::whereIn('id', (clone $mine)->whereNotNull('category_id')->select('category_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'buyers' => $this->me()->isAdmin() ? \App\Models\User::whereIn('id', (clone $mine)->select('buyer_id'))->orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
