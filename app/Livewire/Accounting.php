<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Services\ReportService;
use App\Support\Audit;
use App\Support\Sheet;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Accounting export')]
class Accounting extends Component
{
    use Page;

    public string $from = '';

    public string $to = '';

    public bool $again = false;

    public function mount(): void
    {
        $this->need('invoices', 'A');
        abort_unless($this->me()->isSourcing(), 403);
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function download(ReportService $reports, string $format = 'xlsx')
    {
        $this->need('invoices', 'A');
        if ($this->from === '' || $this->to === '' || $this->to < $this->from) {
            $this->toast('Choose a period: the end date cannot be before the start date.', 'alert');

            return null;
        }
        [$head, $rows, $invoices] = $reports->accounting($this->me(), $this->from, $this->to, $this->again);
        if ($invoices->isEmpty()) {
            $this->toast('No approved or paid invoices in this period are waiting for export.', 'alert');

            return null;
        }
        \App\Models\Invoice::whereIn('id', $invoices->pluck('id'))->whereNull('exported_at')->update(['exported_at' => now()]);
        Audit::log('Invoice', "Exported {$invoices->count()} invoice(s) to accounting for {$this->from} to {$this->to}");

        return Sheet::download("accounting-{$this->from}-to-{$this->to}", $head, $rows, $format === 'csv' ? 'csv' : 'xlsx');
    }

    public function render(ReportService $reports)
    {
        $ok = $this->from !== '' && $this->to !== '' && $this->to >= $this->from;
        [, , $invoices] = $ok ? $reports->accounting($this->me(), $this->from, $this->to, $this->again) : [[], [], collect()];

        return view('livewire.accounting', ['invoices' => $invoices, 'total' => (int) $invoices->sum('total_cents')]);
    }
}
