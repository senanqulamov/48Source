<?php

namespace App\Livewire\Contracts;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Contract;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Contracts')]
class Index extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['number', 'title', 'status', 'ends_on', 'released_cents', 'created_at'];
    }

    private function query()
    {
        return Contract::visibleTo($this->me())->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhere('title', 'like', "%{$this->q}%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->q}%"))));
    }

    protected function exportData(): array
    {
        $rows = $this->query()->with('supplier')->orderByDesc('id')->get()->map(fn (Contract $c) => [$c->number, $c->title, $c->supplier->name, $c->statusLabel(), $c->starts_on->format('Y-m-d'), $c->ends_on->format('Y-m-d'), Money::input($c->ceiling_cents), Money::input($c->released_cents), $c->currency]);

        return ['contracts', ['Reference', 'Title', 'Supplier', 'Status', 'Starts', 'Ends', 'Ceiling', 'Released', 'Currency'], $rows];
    }

    public function render()
    {
        $rows = $this->query()->with('supplier')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());

        return view('livewire.contracts.index', ['rows' => $rows, 'counts' => $this->query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'all' => Contract::visibleTo($this->me())->count()]);
    }
}
