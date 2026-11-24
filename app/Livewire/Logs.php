<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\ActivityLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Activity log')]
class Logs extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['created_at', 'actor_name', 'area'];
    }

    protected function exportData(): array
    {
        $rows = $this->scope()->latest('created_at')->limit(5000)->get()->map(fn (ActivityLog $l) => [$l->created_at->format('Y-m-d H:i:s'), $l->actor_name, $l->area, $l->action, $l->subject_label]);

        return ['activity-log', ['When', 'User', 'Area', 'Event', 'Record'], $rows];
    }

    /** Administrators read the whole log. Everyone else reads only what they did themselves. */
    private function scope()
    {
        $me = $this->me();

        return ActivityLog::query()->when(! $me->allowed('system'), fn ($q) => $q->where('user_id', $me->id));
    }

    public function render()
    {
        $all = $this->me()->allowed('system');
        $areas = $this->scope()->distinct()->orderBy('area')->pluck('area')->all();
        $rows = $this->scope()
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('action', 'like', "%{$this->q}%")->orWhere('actor_name', 'like', "%{$this->q}%")->orWhere('subject_label', 'like', "%{$this->q}%")))
            ->when($this->facet('area', $areas), fn ($q, $a) => $q->whereIn('area', $a))
            ->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')
            ->paginate($this->perPage());

        return view('livewire.logs', ['rows' => $rows, 'areas' => array_combine($areas, $areas), 'all' => $this->scope()->count(), 'everyone' => $all]);
    }
}
