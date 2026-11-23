<?php

namespace App\Livewire\Rfis;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Rfi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Information requests')]
class Index extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['number', 'title', 'status', 'deadline_at', 'created_at'];
    }

    public function render()
    {
        $u = $this->me();
        $base = fn () => Rfi::visibleTo($u)->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhere('title', 'like', "%{$this->q}%")));
        $rows = $base()->with(['buyer', 'responses' => fn ($r) => $u->role === 'supplier' ? $r->where('supplier_id', $u->supplier_id) : $r])->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());

        return view('livewire.rfis.index', ['rows' => $rows, 'counts' => $base()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'all' => Rfi::visibleTo($u)->count(), 'sourcing' => $u->isSourcing()]);
    }
}
