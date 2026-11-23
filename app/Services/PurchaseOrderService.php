<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;

class PurchaseOrderService
{
    /** The next step this user may take on the order, or null. */
    public function nextFor(PurchaseOrder $po, User $user): ?array
    {
        $step = PurchaseOrder::NEXT[$po->status] ?? null;
        if (! $step || ! $user->allowed('orders', 'E')) {
            return null;
        }
        $mine = $step['side'] === 'buyer'
            ? ($user->isAdmin() || ($user->role === 'buyer' && $po->buyer_id === $user->id))
            : ($user->role === 'supplier' && $user->supplier_id === $po->supplier_id);

        return $mine ? $step : null;
    }

    public function advance(PurchaseOrder $po, User $user): PurchaseOrder
    {
        $step = $this->nextFor($po, $user) ?? throw new BusinessRule('You cannot move this order to its next step.');
        $po->update(['status' => $step['to'], $step['stamp'] => now()]);
        $label = PurchaseOrder::STATUSES[$step['to']];
        if ($step['side'] === 'buyer') {
            Notifier::supplier($po->supplier_id, "Purchase order {$po->number} is now {$label}.", route('orders.show', $po), 'Order');
        } else {
            Notifier::send($po->buyer, "{$po->supplier->name}: {$po->number} is now {$label}.", route('orders.show', $po), 'Order');
        }
        Audit::log('Order', "Order moved to {$label}", $po, supplierId: $po->supplier_id);

        return $po;
    }

    public function cancel(PurchaseOrder $po, User $user, string $reason): PurchaseOrder
    {
        $mine = $user->isAdmin() || ($user->role === 'buyer' && $po->buyer_id === $user->id);
        if (! $mine || ! $user->allowed('orders', 'E')) {
            throw new BusinessRule('You cannot cancel this order.');
        }
        if (! in_array($po->status, ['draft', 'issued'], true)) {
            throw new BusinessRule('An order can be cancelled only before the supplier acknowledges it.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new BusinessRule('Give a short reason for cancelling.');
        }
        $wasIssued = $po->status === 'issued';
        $po->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        if ($wasIssued) {
            Notifier::supplier($po->supplier_id, "Purchase order {$po->number} was cancelled: ".trim($reason), route('orders.show', $po), 'Order');
        }
        Audit::log('Order', 'Cancelled order: '.trim($reason), $po, supplierId: $po->supplier_id);

        return $po;
    }
}
