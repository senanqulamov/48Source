<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Models\Category;
use App\Services\ReportService;
use App\Support\Chart;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Spend & reports')]
class Reports extends Component
{
    use Page;

    #[Url(except: 'spend')]
    public string $tab = 'spend';

    #[Url]
    public int $year = 0;

    /** KPI reports: how many months back. */
    #[Url(except: 12)]
    public int $period = 12;

    /** Which of the three pages this is: spend, savings or kpis. */
    public string $page = 'spend';

    public array $b = ['category_id' => '', 'amount' => ''];

    public function mount(string $page = 'spend'): void
    {
        $this->page = in_array($page, ['spend', 'savings', 'kpis'], true) ? $page : 'spend';
        $this->tab = $this->page === 'spend' ? (in_array($this->tab, ['spend', 'budgets'], true) ? $this->tab : 'spend') : $this->page;
        $this->need('spend');
        abort_unless($this->me()->isSourcing(), 403);
        $this->year = $this->year ?: (int) now()->format('Y');
    }

    public function saveBudget(ReportService $reports): void
    {
        $this->need('spend', 'E');
        if ($this->attempt(function () use ($reports) {
            try {
                $cents = Money::parse($this->b['amount']);
            } catch (\InvalidArgumentException) {
                throw new \App\Exceptions\BusinessRule('The budget is not a valid amount.');
            }
            $reports->setBudget($this->year, (int) $this->b['category_id'], $cents);
        }, 'Budget saved.')) {
            $this->reset('b');
            $this->modal = null;
        }
    }

    public function export(ReportService $reports)
    {
        $s = $reports->spend($this->me(), $this->year);
        $rows = $s['bySupplier']->map(fn ($r) => ['Supplier', $r['name'], $r['orders'], number_format($r['cents'] / 100, 2, '.', '')])->merge($s['byCategory']->map(fn ($r) => ['Category', $r['name'], $r['orders'], number_format($r['cents'] / 100, 2, '.', '')]))->all();

        return \App\Support\Sheet::download("spend-{$this->year}", ['Group', 'Name', 'Orders', 'Committed'], $rows, 'xlsx');
    }

    public function render(ReportService $reports)
    {
        $u = $this->me();
        $spend = $reports->spend($u, $this->year);
        $rank = fn ($rows) => Chart::rank($rows->take(6)->map(fn ($r) => [$r['name'], $r['cents'], Money::format($r['cents'], 'USD', false)])->all());

        $an = $this->page === 'spend' ? $reports->analysis($u, $this->year) : null;
        $allBudgets = $this->page === 'spend' ? $reports->budgets($u, $this->year) : collect();

        return view('livewire.reports', [
            'kr' => $this->tab === 'kpis' ? $reports->kpiReport($u, $this->period) : null,
            'an' => $an, 'over' => $allBudgets->filter(fn ($b) => $b['used'] > $b['amount'])->count(), 'budgetCount' => $allBudgets->count(),
            'spend' => $spend, 'area' => Chart::area(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], array_map(fn ($c) => intdiv($c, 100), array_values($spend['months'])), '$'),
            'bySupplier' => $rank($spend['bySupplier']), 'byCategory' => $rank($spend['byCategory']),
            'budgets' => $this->tab === 'budgets' ? $reports->budgets($u, $this->year) : collect(), 'savings' => $this->tab === 'savings' ? $reports->savings($u, $this->year) : null,
            'kpis' => $this->tab === 'kpis' ? $reports->kpis($u, $this->year) : [], 'categories' => $this->modal === 'budget' ? Category::options() : [],
            'years' => range((int) now()->format('Y'), (int) now()->format('Y') - 3),
        ]);
    }
}
