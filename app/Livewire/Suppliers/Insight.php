<?php

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Supplier;
use App\Models\SupplierAction;
use App\Models\SupplierDocument;
use App\Services\SupplierInsightService;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Supplier 360: every supplier with its risk, documents, scorecard and open actions. */
#[Layout('components.layouts.app')]
#[Title('Supplier 360')]
class Insight extends Component
{
    use Page, WithTable;

    public function mount(): void
    {
        abort_unless($this->me()->isSourcing(), 403);
    }

    protected function sortable(): array
    {
        return ['name', 'city', 'status', 'rating_tenths'];
    }

    public function render(SupplierInsightService $insight)
    {
        $today = now()->toDateString();
        $rows = Supplier::query()->with('category', 'documents', 'scorecards', 'actions')
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->q}%")->orWhere('city', 'like', "%{$this->q}%")))
            ->when($this->facet('status', array_keys(Supplier::STATUSES)), fn ($q, $s) => $q->whereIn('status', $s))
            ->orderBy($this->sortCol('name'), ($this->sort === '' ? 'asc' : $this->sortDir()))->orderBy('id')
            ->paginate($this->perPage());
        $risk = [];
        foreach ($rows as $s) {
            $risk[$s->id] = $insight->risk($s);
        }

        // The band above the list covers all suppliers, so it is worked out once and kept for ten minutes.
        $levels = Cache::remember('sup360.levels', 600, function () use ($insight) {
            $n = ['high' => 0, 'medium' => 0];
            Supplier::query()->with('documents', 'scorecards', 'actions')->chunkById(200, function ($chunk) use (&$n, $insight) {
                foreach ($chunk as $s) {
                    $level = $insight->risk($s)['level'];
                    if (isset($n[$level])) {
                        $n[$level]++;
                    }
                }
            });

            return $n;
        });

        return view('livewire.suppliers.insight', [
            'rows' => $rows,
            'risk' => $risk,
            'all' => Supplier::count(),
            'strip' => [
                ['l' => 'High-risk suppliers', 'v' => $levels['high'], 's' => $levels['medium'].' medium', 'c' => $levels['high'] ? 'bad' : ''],
                ['l' => 'Documents expired', 'v' => SupplierDocument::whereNotNull('expires_on')->whereDate('expires_on', '<', $today)->count(), 's' => SupplierDocument::whereNotNull('expires_on')->whereDate('expires_on', '>=', $today)->whereDate('expires_on', '<=', now()->addDays(30)->toDateString())->count().' expiring in 30 days'],
                ['l' => 'Waiting for approval', 'v' => Supplier::where('status', 'pending')->count(), 's' => 'registered, not yet approved', 'href' => route('suppliers.index', ['status' => 'pending'])],
                ['l' => 'Open action plans', 'v' => SupplierAction::where('status', 'open')->count(), 's' => SupplierAction::where('status', 'open')->whereDate('due_on', '<', $today)->count().' overdue'],
            ],
        ]);
    }
}
