<?php

namespace App\Livewire\Orders;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\PurchaseOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Purchase orders')]
class Index extends Component
{
    use Page, WithTable;

    protected function sortable(): array
    {
        return ['number', 'status', 'total_cents', 'expected_at', 'created_at'];
    }

    protected function exportData(): array
    {
        $rows = $this->picked(PurchaseOrder::visibleTo($this->me()), 'number')->with('supplier', 'rfq', 'buyer')->when($this->status !== '' && ! $this->onlySelected, fn ($q) => $q->where('status', $this->status))->orderByDesc('id')->get()
            ->map(fn (PurchaseOrder $p) => [$p->number, $p->supplier->name, $p->rfq->number, $p->buyer->name, \App\Support\Money::input($p->total_cents), $p->currency, $p->statusLabel(), $p->created_at->format('Y-m-d'), $p->expected_at?->format('Y-m-d')]);

        return ['purchase-orders', ['Order', 'Supplier', 'RFQ', 'Buyer', 'Amount', 'Currency', 'Status', 'Issued', 'Delivery due'], $rows];
    }

    public function render()
    {
        $u = $this->me();
        $base = fn () => PurchaseOrder::visibleTo($u);
        $sups = $this->facet('sup', \App\Models\Supplier::pluck('id')->all());
        $rows = $base()->with('supplier', 'rfq', 'buyer')->when($sups && $u->role !== 'supplier', fn ($q) => $q->whereIn('supplier_id', $sups))
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%{$this->q}%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->q}%"))->orWhereHas('rfq', fn ($r) => $r->where('title', 'like', "%{$this->q}%")->orWhere('number', 'like', "%{$this->q}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sortCol('created_at'), $this->sortDir())->orderByDesc('id')
            ->paginate($this->perPage());

        return view('livewire.orders.index', ['rows' => $rows, 'counts' => $base()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'supplierSide' => $u->role === 'supplier',
            'sups' => $u->role === 'supplier' ? [] : \App\Models\Supplier::whereIn('id', $base()->select('supplier_id'))->orderBy('name')->pluck('name', 'id')->all(), 'pageTotal' => $rows->sum('total_cents')]);
    }
}
