<?php

namespace App\Livewire\Procurement\PurchaseOrders;

use App\Models\ProcurementPurchaseOrder;
use App\Services\Procurement\AwardAndPurchaseOrderService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public ProcurementPurchaseOrder $purchaseOrder;

    public function mount(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin()
            || $purchaseOrder->buyer_id === $user->id
            || $purchaseOrder->supplier_id === $user->id), 403);
        $this->purchaseOrder = $purchaseOrder->load(['lines', 'supplier', 'buyer', 'request', 'quote']);
    }

    public function advance(AwardAndPurchaseOrderService $service): void
    {
        $next = $this->purchaseOrder->fresh()->nextStatus();
        abort_unless($next !== null, 422);
        $this->purchaseOrder = $service->transition($this->purchaseOrder, auth()->user(), $next)
            ->load(['lines', 'supplier', 'buyer', 'request', 'quote']);
    }

    public function render(): View
    {
        $user = auth()->user();
        $next = $this->purchaseOrder->nextStatus();
        $canAdvance = $next !== null && (
            in_array($next, ['issued', 'delivered'], true)
                ? ($user->isAdmin() || ($user->isBuyer() && $this->purchaseOrder->buyer_id === $user->id))
                : $this->purchaseOrder->supplier_id === $user->id
        );

        return view('livewire.procurement.purchase-orders.show', compact('canAdvance', 'next'))
            ->layout('layouts.app');
    }
}
