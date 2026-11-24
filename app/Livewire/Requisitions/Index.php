<?php

namespace App\Livewire\Requisitions;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Requisition;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Requisitions')]
class Index extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['number', 'title', 'status', 'total_cents', 'needed_by', 'created_at'];
    }

    private function query()
    {
        return Requisition::visibleTo($this->me())->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhere('title', 'like', "%{$this->q}%")->orWhereHas('requester', fn ($r) => $r->where('name', 'like', "%{$this->q}%"))));
    }

    protected function exportData(): array
    {
        $rows = $this->query()->with('requester')->orderByDesc('id')->get()->map(fn (Requisition $r) => [$r->number, $r->title, $r->requester->name, $r->statusLabel(), Money::input($r->total_cents), $r->currency, $r->needed_by?->format('Y-m-d'), $r->created_at->format('Y-m-d')]);

        return ['requisitions', ['Reference', 'Title', 'Requester', 'Status', 'Estimated total', 'Currency', 'Needed by', 'Created'], $rows];
    }

    public function render()
    {
        $rows = $this->query()->with('requester', 'approvals')->withCount('items')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());

        $me = $this->me();
        $mine = Requisition::visibleTo($me)->where('status', 'pending')->where('requester_id', '!=', $me->id);
        $waiting = $me->allowed('requisitions', 'A') ? (clone $mine)->count() : 0;
        $month = now()->startOfMonth();
        $strip = [
            ['l' => 'Waiting for my approval', 'v' => $waiting, 's' => $waiting ? \App\Support\Money::format((int) (clone $mine)->sum('total_cents'), 'USD', false) : 'nothing to do', 'c' => $waiting ? 'warn' : ''],
            ['l' => 'In approval', 'v' => Requisition::visibleTo($me)->where('status', 'pending')->count(), 's' => 'across all approvers'],
            ['l' => 'Approved, not yet ordered', 'v' => Requisition::visibleTo($me)->where('status', 'approved')->count(), 's' => 'ready to source'],
            ['l' => 'Rejected this month', 'v' => Requisition::visibleTo($me)->where('status', 'rejected')->where('decided_at', '>=', $month)->count(), 's' => 'with a reason recorded'],
        ];

        return view('livewire.requisitions.index', ['strip' => $strip, 'rows' => $rows, 'counts' => $this->query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'all' => Requisition::visibleTo($this->me())->count()]);
    }
}
