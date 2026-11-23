<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Goods receipts: what arrived against a purchase order, line by line. */
class ReceiptService
{
    /** Accepted quantity so far per order item id, in thousandths. @return array<int, int> */
    public function received(PurchaseOrder $po): array
    {
        $got = DB::table('goods_receipt_items')->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.purchase_order_id', $po->id)->groupBy('purchase_order_item_id')->selectRaw('purchase_order_item_id, sum(accepted_milli) as q')->pluck('q', 'purchase_order_item_id');
        $out = [];
        foreach ($po->items as $item) {
            $out[$item->id] = (int) ($got[$item->id] ?? 0);
        }

        return $out;
    }

    /** Share of the ordered quantity that has arrived, 0 to 100. */
    public function percent(PurchaseOrder $po): int
    {
        $ordered = (int) $po->items->sum('quantity_milli');

        return $ordered ? (int) min(100, intdiv(array_sum($this->received($po)) * 100, $ordered)) : 0;
    }

    /**
     * @param  array<int|string, string>  $accepted  order item id => accepted quantity
     * @param  array<int|string, string>  $rejected  order item id => rejected quantity
     */
    public function record(PurchaseOrder $po, User $user, array $accepted, array $rejected = [], ?string $date = null, ?string $note = null): GoodsReceipt
    {
        if (! in_array($po->status, ['acknowledged', 'in_transit', 'delivered'], true)) {
            throw new BusinessRule('Goods can be received once the supplier has acknowledged the order and before it is invoiced.');
        }
        if (! $user->isSourcing() || ! ($user->isAdmin() || $po->buyer_id === $user->id)) {
            throw new BusinessRule('Only the buyer of this order can record a receipt.');
        }
        $date = $date ?: now()->toDateString();
        if ($date > now()->toDateString()) {
            throw new BusinessRule('The receipt date cannot be in the future.');
        }
        $got = $this->received($po);
        $lines = [];
        foreach ($po->items as $item) {
            try {
                $ok = Money::parseQty($accepted[$item->id] ?? null) ?? 0;
                $bad = Money::parseQty($rejected[$item->id] ?? null) ?? 0;
            } catch (InvalidArgumentException) {
                throw new BusinessRule("A quantity for \"{$item->name}\" is not a number.");
            }
            if ($ok < 0 || $bad < 0) {
                throw new BusinessRule("Quantities for \"{$item->name}\" cannot be negative.");
            }
            if ($ok === 0 && $bad === 0) {
                continue;
            }
            $left = $item->quantity_milli - $got[$item->id];
            if ($ok > $left) {
                throw new BusinessRule("\"{$item->name}\": only " . Money::qty($left) . " {$item->unit} is still to come. You entered " . Money::qty($ok) . '.');
            }
            $lines[] = ['purchase_order_item_id' => $item->id, 'accepted_milli' => $ok, 'rejected_milli' => $bad];
        }
        if (! $lines) {
            throw new BusinessRule('Enter a received or rejected quantity for at least one item.');
        }

        return DB::transaction(function () use ($po, $user, $lines, $date, $note) {
            $grn = GoodsReceipt::create(['number' => Sequence::next('GRN'), 'purchase_order_id' => $po->id, 'received_by' => $user->id, 'received_on' => $date, 'note' => trim((string) $note) ?: null]);
            $grn->items()->createMany($lines);
            $pct = $this->percent($po);
            $rejected = array_sum(array_column($lines, 'rejected_milli'));
            Notifier::supplier($po->supplier_id, "Receipt {$grn->number} recorded on {$po->number}: {$pct}% of the order has arrived" . ($rejected ? ', some items were rejected.' : '.'), route('orders.show', $po), 'Receipt');
            Audit::log('Receipt', "Recorded receipt {$grn->number} ({$pct}% received)", $po, $po->number, supplierId: $po->supplier_id);
            if ($pct >= 100 && $po->status === 'in_transit') {
                $po->update(['status' => 'delivered', 'delivered_at' => now()]);
                Audit::log('Order', 'Order moved to Delivered after full receipt', $po, $po->number, supplierId: $po->supplier_id);
            }

            return $grn->fresh();
        });
    }
}
