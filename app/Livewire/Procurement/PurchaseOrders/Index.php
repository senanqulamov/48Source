<?php

namespace App\Livewire\Procurement\PurchaseOrders;

use App\Models\ProcurementPurchaseOrder;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function render(): View
    {
        $actor = auth()->user();
        abort_unless($actor && ($actor->isAdmin() || $actor->isBuyer() || $actor->isSupplier()), 403);

        $orders = ProcurementPurchaseOrder::query()->with(['buyer', 'supplier'])
            ->when(! $actor->isAdmin(), function ($query) use ($actor) {
                $query->where(function ($visible) use ($actor) {
                    if ($actor->isBuyer()) {
                        $visible->where('buyer_id', $actor->id);
                    }
                    if ($actor->isSupplier()) {
                        $actor->isBuyer()
                            ? $visible->orWhere('supplier_id', $actor->id)
                            : $visible->where('supplier_id', $actor->id);
                    }
                });
            })
            ->when($this->search !== '', fn ($query) => $query->where('id', 'like', '%'.trim($this->search).'%'))
            ->latest()->paginate(15);

        return view('livewire.procurement.purchase-orders.index', compact('orders'))
            ->layout('layouts.app');
    }
}
