<?php

namespace App\Services\Procurement;

use App\Models\ProcurementPurchaseOrder;
use App\Models\Quote;
use App\Models\Request;
use App\Models\User;
use App\Models\WorkflowEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates one immutable draft procurement PO per RFQ, with an auditable award.
 * Not the existing marketplace Order model.
 */
final class AwardAndPurchaseOrderService
{
    public function award(Request $rfq, int $quoteId, User $actor, ?string $justification = null): ProcurementPurchaseOrder
    {
        abort_unless($actor->isAdmin() || ($actor->isBuyer() && $rfq->buyer_id === $actor->id), 403);

        return DB::transaction(function () use ($rfq, $quoteId, $actor, $justification) {
            $rfq = Request::query()->lockForUpdate()->findOrFail($rfq->id);
            if ($rfq->status !== 'closed') {
                throw ValidationException::withMessages(['award' => 'Close the RFQ before awarding it.']);
            }
            if (ProcurementPurchaseOrder::query()->where('request_id', $rfq->id)->exists()) {
                throw ValidationException::withMessages(['award' => 'This RFQ already has a procurement purchase order.']);
            }

            $rfqItems = $rfq->items()->get()->keyBy('id');
            if ($rfqItems->isEmpty()) {
                throw ValidationException::withMessages(['award' => 'Cannot award an RFQ without items.']);
            }
            $quotes = Quote::query()->where('request_id', $rfq->id)
                ->where('status', 'submitted')->with('items')->lockForUpdate()->get();
            $candidate = $quotes->firstWhere('id', $quoteId);
            if (! $candidate) {
                throw ValidationException::withMessages(['award' => 'Select a submitted quotation belonging to this RFQ.']);
            }
            if ($candidate->valid_until && $candidate->valid_until->isPast()) {
                throw ValidationException::withMessages(['award' => 'The selected quote has expired.']);
            }

            $currency = strtoupper(trim((string) $candidate->currency));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw ValidationException::withMessages(['award' => 'The selected quote must have a valid currency.']);
            }

            // Only comparable complete quotations in the same currency are scored.
            // Reject mixed currencies instead of silently comparing nominal amounts.
            if ($quotes->contains(fn (Quote $q) => strtoupper(trim((string) $q->currency)) !== $currency)) {
                throw ValidationException::withMessages(['award' => 'Quotes use different currencies; normalize them before award.']);
            }

            $totals = [];
            $snapshots = [];
            foreach ($quotes as $quote) {
                $lineIds = $quote->items->pluck('request_item_id')->map(fn ($id) => (int) $id);
                if ($lineIds->count() !== $rfqItems->count()
                    || $lineIds->unique()->count() !== $rfqItems->count()
                    || $lineIds->diff($rfqItems->keys())->isNotEmpty()) {
                    if ($quote->id === $candidate->id) {
                        throw ValidationException::withMessages(['award' => 'The awarded quotation must cover every requested line exactly once.']);
                    }
                    continue;
                }
                $total = 0;
                $lines = [];
                foreach ($quote->items as $item) {
                    /** @var \App\Models\RequestItem $requested */
                    $requested = $rfqItems->get((int) $item->request_item_id);
                    $quantity = (float) $requested->quantity;
                    $unit = (float) $item->unit_price;
                    $tax = (float) $item->tax_rate;
                    if ($quantity <= 0 || $unit <= 0 || $tax < 0 || $tax > 100) {
                        throw ValidationException::withMessages(['award' => 'Quotation contains invalid quantity, price or tax.']);
                    }
                    $lineTotal = round($quantity * $unit * (1 + $tax / 100), 2);
                    $total += $lineTotal;
                    $lines[] = [
                        'request_item_id' => $requested->id,
                        'description' => (string) ($requested->product_name ?: 'Requested item'),
                        'quantity' => $quantity,
                        'unit_price' => $unit,
                        'tax_rate' => $tax,
                        'line_total' => $lineTotal,
                    ];
                }
                $totals[$quote->id] = round($total, 2);
                $snapshots[$quote->id] = $lines;
            }

            if (! isset($totals[$quoteId])) {
                throw ValidationException::withMessages(['award' => 'The selected quotation is not complete.']);
            }

            $lowest = min($totals);
            $reason = trim((string) $justification);
            if ($totals[$quoteId] > $lowest + 0.005 && mb_strlen($reason) < 15) {
                throw ValidationException::withMessages([
                    'justification' => 'Choosing a non-cheapest quotation requires a justification of at least 15 characters.',
                ]);
            }

            $purchaseOrder = ProcurementPurchaseOrder::create([
                'request_id' => $rfq->id,
                'quote_id' => $candidate->id,
                'buyer_id' => $rfq->buyer_id,
                'supplier_id' => $candidate->supplier_id,
                'awarded_by' => $actor->id,
                'award_justification' => $reason !== '' ? $reason : null,
                'currency' => $currency,
                'total_amount' => $totals[$quoteId],
                'status' => 'draft',
            ]);
            $purchaseOrder->lines()->createMany($snapshots[$quoteId]);

            $candidate->update(['status' => 'accepted']);
            Quote::query()->where('request_id', $rfq->id)->where('id', '!=', $quoteId)
                ->where('status', 'submitted')->update(['status' => 'rejected']);
            $rfq->update(['status' => 'awarded']);

            WorkflowEvent::create([
                'eventable_type' => Request::class,
                'eventable_id' => $rfq->id,
                'user_id' => $actor->id,
                'event_type' => 'award_approved',
                'from_state' => 'closed',
                'to_state' => 'awarded',
                'description' => 'Awarded quote #'.$quoteId.'; created procurement PO #'.$purchaseOrder->id,
                'occurred_at' => now(),
                'metadata' => [
                    'quote_id' => $quoteId, 'purchase_order_id' => $purchaseOrder->id,
                    'awarded_total' => $totals[$quoteId], 'lowest_comparable_total' => $lowest,
                    'currency' => $currency, 'justification' => $reason,
                ],
            ]);

            return $purchaseOrder;
        }, 3);
    }

    public function transition(ProcurementPurchaseOrder $order, User $actor, string $next): ProcurementPurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor, $next) {
            $order = ProcurementPurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $expected = $order->nextStatus();
            if ($expected === null || $next !== $expected) {
                throw ValidationException::withMessages(['status' => 'Invalid purchase order status transition.']);
            }
            $buyerAction = in_array($next, ['issued', 'delivered'], true);
            $authorized = $buyerAction
                ? ($actor->isAdmin() || ($actor->isBuyer() && $order->buyer_id === $actor->id))
                : $order->supplier_id === $actor->id;
            abort_unless($authorized, 403);

            $before = $order->status;
            $timestamps = [
                'issued' => 'issued_at',
                'acknowledged' => 'acknowledged_at',
                'in_transit' => 'in_transit_at',
                'delivered' => 'delivered_at',
                'invoiced' => 'invoiced_at',
            ];
            $order->update(['status' => $next, $timestamps[$next] => now()]);
            WorkflowEvent::create([
                'eventable_type' => ProcurementPurchaseOrder::class,
                'eventable_id' => $order->id,
                'user_id' => $actor->id,
                'event_type' => 'procurement_po_status',
                'from_state' => $before,
                'to_state' => $next,
                'description' => 'Procurement PO #'.$order->id.' moved to '.$next,
                'occurred_at' => now(),
                'metadata' => ['request_id' => $order->request_id, 'quote_id' => $order->quote_id],
            ]);
            return $order;
        }, 3);
    }
}
