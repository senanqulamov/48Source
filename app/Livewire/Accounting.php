<?php

namespace App\Livewire;

use App\Support\Money;
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

    /** The data sets the page offers, with the columns each file contains. */
    public const SETS = [
        'orders' => ['Purchase orders', 'One row per order line, with supplier, department and delivery date.', ['PO number', 'RFQ', 'Supplier ID', 'Supplier', 'Department', 'Category', 'Line', 'Item', 'Quantity', 'Unit', 'Unit price', 'Line total', 'Currency', 'Order date', 'Delivery date', 'Status']],
        'invoices' => ['Supplier invoices', 'Invoices with their match result and payment status, for accounts payable.', ['Invoice ID', 'Invoice number', 'PO number', 'Supplier ID', 'Supplier', 'Amount', 'Currency', 'Invoice date', 'Due date', 'Match status', 'Paid on']],
        'receipts' => ['Goods receipts', 'What was received against each order, and by whom.', ['Receipt', 'PO number', 'Date', 'Received by', 'Lines', 'Note']],
        'suppliers' => ['Supplier master data', 'Supplier records with status, contact and rating.', ['Supplier ID', 'Name', 'Category', 'City', 'Status', 'Contact', 'Email', 'Phone', 'Rating']],
        'contracts' => ['Contracts', 'Contract values, dates and how much is used.', ['Contract', 'Title', 'Supplier ID', 'Supplier', 'Value', 'Used', 'Currency', 'Start', 'End', 'Status']],
    ];

    /** @return array<int, array<int, mixed>> */
    private function rowsOf(string $set): array
    {
        $u = $this->me();
        $m = fn (?int $c) => $c === null ? '' : Money::input($c);

        return match ($set) {
            'orders' => \App\Models\PurchaseOrder::visibleTo($u)->whereNotIn('status', ['draft', 'cancelled'])->with('items', 'supplier:id,name', 'buyer:id,department', 'rfq:id,number,category_id', 'rfq.category:id,name')->latest('id')->limit(5000)->get()
                ->flatMap(fn ($o) => $o->items->values()->map(fn ($i, $n) => [$o->number, $o->rfq?->number, $o->supplier_id, $o->supplier->name, $o->buyer?->department, $o->rfq?->category?->name, $n + 1, $i->name, Money::qtyInput($i->quantity_milli), $i->unit, $m($i->unit_price_cents), $m($i->total_cents), $o->currency, $o->created_at->format('Y-m-d'), $o->expected_at?->format('Y-m-d'), $o->statusLabel()]))->all(),
            'invoices' => \App\Models\Invoice::visibleTo($u)->with('supplier:id,name', 'order:id,number')->latest('id')->limit(5000)->get()
                ->map(fn ($i) => [$i->number, $i->supplier_ref, $i->order?->number, $i->supplier_id, $i->supplier->name, $m($i->total_cents), $i->currency, $i->issued_on?->format('Y-m-d'), $i->due_on?->format('Y-m-d'), $i->statusLabel(), $i->paid_at?->format('Y-m-d')])->all(),
            'receipts' => \App\Models\GoodsReceipt::whereIn('purchase_order_id', \App\Models\PurchaseOrder::visibleTo($u)->select('id'))->with('order:id,number', 'receiver:id,name')->withCount('items')->latest('id')->limit(5000)->get()
                ->map(fn ($r) => [$r->number, $r->order?->number, $r->received_on instanceof \DateTimeInterface ? $r->received_on->format('Y-m-d') : (string) $r->received_on, $r->receiver?->name, $r->items_count, $r->note])->all(),
            'suppliers' => \App\Models\Supplier::with('category:id,name')->orderBy('name')->limit(10000)->get()
                ->map(fn ($s) => [$s->id, $s->name, $s->category?->name, $s->city, $s->statusLabel(), $s->contact_name, $s->email, $s->phone, number_format($s->rating_tenths / 10, 1)])->all(),
            'contracts' => \App\Models\Contract::visibleTo($u)->with('supplier:id,name')->latest('id')->limit(5000)->get()
                ->map(fn ($c) => [$c->number, $c->title, $c->supplier_id, $c->supplier->name, $m($c->ceiling_cents), $m($c->released_cents), $c->currency, $c->starts_on?->format('Y-m-d'), $c->ends_on?->format('Y-m-d'), $c->statusLabel()])->all(),
            default => [],
        };
    }

    /** One data set as CSV or JSON for an ERP or accounting system. Every download is logged. */
    public function exportSet(string $set, string $format = 'csv')
    {
        abort_unless(isset(self::SETS[$set]), 404);
        [$title, , $head] = self::SETS[$set];
        $rows = $this->rowsOf($set);
        \App\Support\Audit::log('Export', "Exported {$title} as ".strtoupper($format === 'json' ? 'json' : 'csv').' ('.count($rows).' rows)');
        $name = 'erp-'.$set.'-'.now()->format('Ymd-His');
        if ($format === 'json') {
            $data = array_map(fn ($r) => array_combine($head, $r), $rows);

            return response()->streamDownload(function () use ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }, $name.'.json', ['Content-Type' => 'application/json']);
        }

        return \App\Support\Sheet::download($name, $head, $rows, 'csv');
    }

    public function render(ReportService $reports)
    {
        $ok = $this->from !== '' && $this->to !== '' && $this->to >= $this->from;
        [, , $invoices] = $ok ? $reports->accounting($this->me(), $this->from, $this->to, $this->again) : [[], [], collect()];

        return view('livewire.accounting', ['history' => \App\Models\ActivityLog::where('area', 'Export')->latest('created_at')->limit(15)->get(), 'invoices' => $invoices, 'total' => (int) $invoices->sum('total_cents')]);
    }
}
