<?php

namespace App\Livewire\Invoices;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Invoices & receipts')]
class Index extends Component
{
    use Page, WithTable;

    #[Url(except: 'invoices')]
    public string $tab = 'invoices';

    protected function sortable(): array
    {
        return ['number', 'status', 'total_cents', 'issued_on', 'due_on', 'created_at'];
    }

    private function query()
    {
        return Invoice::visibleTo($this->me())->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhere('supplier_ref', 'like', "%{$this->q}%")
            ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->q}%"))->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$this->q}%"))));
    }

    protected function exportData(): array
    {
        $rows = $this->query()->with('supplier', 'order')->orderByDesc('id')->get()->map(fn (Invoice $i) => [$i->number, $i->supplier_ref, $i->supplier->name, $i->order->number, $i->statusLabel(), Money::input($i->total_cents), $i->currency, $i->issued_on->format('Y-m-d'), $i->due_on?->format('Y-m-d'), $i->paid_at?->format('Y-m-d')]);

        return ['invoices', ['Reference', 'Supplier invoice no.', 'Supplier', 'Order', 'Status', 'Amount', 'Currency', 'Invoice date', 'Due', 'Paid'], $rows];
    }

    public function render()
    {
        $u = $this->me();
        $rows = $this->query()->with('supplier', 'order')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')->paginate($this->perPage());
        $receipts = $this->tab === 'receipts' ? GoodsReceipt::whereIn('purchase_order_id', PurchaseOrder::visibleTo($u)->select('id'))->with('order.supplier', 'receiver', 'items')->latest('id')->limit(100)->get() : collect();

        return view('livewire.invoices.index', ['rows' => $rows, 'receipts' => $receipts, 'counts' => $this->query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'all' => Invoice::visibleTo($u)->count(), 'receiptCount' => GoodsReceipt::whereIn('purchase_order_id', PurchaseOrder::visibleTo($u)->select('id'))->count(), 'supplierSide' => $u->role === 'supplier',
            'open' => (int) Invoice::visibleTo($u)->whereIn('status', ['matched', 'disputed', 'approved'])->sum('total_cents')]);
    }
}
