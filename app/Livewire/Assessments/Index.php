<?php

namespace App\Livewire\Assessments;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\FieldAssessment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Field assessments')]
class Index extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['number', 'title', 'status', 'due_on', 'created_at'];
    }

    public function render()
    {
        $base = fn () => FieldAssessment::visibleTo($this->me())->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhere('title', 'like', "%{$this->q}%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->q}%"))));
        $rows = $base()->with('supplier', 'rfq', 'assignee')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());

        return view('livewire.assessments.index', ['rows' => $rows, 'counts' => $base()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'all' => FieldAssessment::visibleTo($this->me())->count(), 'sourcing' => $this->me()->isSourcing()]);
    }
}
