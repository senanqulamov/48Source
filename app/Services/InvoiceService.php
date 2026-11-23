<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Supplier invoices and the three-way match: order, receipt, invoice. */
class InvoiceService
{
    public function __construct(private ReceiptService $receipts) {}

    /** Quantity already invoiced per order item id (rejected invoices do not count). @return array<int, int> */
    public function invoiced(PurchaseOrder $po, ?int $exceptInvoiceId = null): array
    {
        return DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')->where('invoices.purchase_order_id', $po->id)->where('invoices.status', '!=', 'rejected')
            ->when($exceptInvoiceId, fn ($q) => $q->where('invoices.id', '!=', $exceptInvoiceId))->groupBy('purchase_order_item_id')->selectRaw('purchase_order_item_id, sum(quantity_milli) as q')->pluck('q', 'purchase_order_item_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @param  array{supplier_ref:string,issued_on:string,due_on?:string}  $data
     * @param  array<int|string, array{quantity?:string,price?:string}>  $lines  order item id => what the invoice says
     */
    public function submit(PurchaseOrder $po, User $user, array $data, array $lines): Invoice
    {
        if ($user->role !== 'supplier' || $user->supplier_id !== $po->supplier_id) {
            throw new BusinessRule('Only the supplier of this order can send an invoice for it.');
        }
        if (! in_array($po->status, ['acknowledged', 'in_transit', 'delivered', 'invoiced'], true)) {
            throw new BusinessRule('An invoice can be sent once the order is acknowledged.');
        }
        $ref = trim($data['supplier_ref'] ?? '');
        if ($ref === '') {
            throw new BusinessRule('Enter your invoice number.');
        }
        if (Invoice::where('supplier_id', $po->supplier_id)->where('supplier_ref', $ref)->exists()) {
            throw new BusinessRule("Invoice number {$ref} was already sent. Each invoice number can be used once.");
        }
        if (empty($data['issued_on']) || $data['issued_on'] > now()->toDateString()) {
            throw new BusinessRule('The invoice date must be today or earlier.');
        }
        if (! empty($data['due_on']) && $data['due_on'] < $data['issued_on']) {
            throw new BusinessRule('The due date cannot be before the invoice date.');
        }
        $rows = [];
        foreach ($po->items as $item) {
            try {
                $qty = Money::parseQty($lines[$item->id]['quantity'] ?? null) ?? 0;
                $price = Money::parse($lines[$item->id]['price'] ?? null);
            } catch (InvalidArgumentException) {
                throw new BusinessRule("The quantity or price for \"{$item->name}\" is not a number.");
            }
            if ($qty === 0) {
                continue;
            }
            if ($qty < 0 || $price === null || $price <= 0) {
                throw new BusinessRule("\"{$item->name}\" needs a quantity and a unit price above zero.");
            }
            $rows[] = ['purchase_order_item_id' => $item->id, 'quantity_milli' => $qty, 'unit_price_cents' => $price, 'total_cents' => Money::lineTotal($price, $qty)];
        }
        if (! $rows) {
            throw new BusinessRule('Invoice at least one item.');
        }

        return DB::transaction(function () use ($po, $user, $data, $ref, $rows) {
            $inv = Invoice::create(['number' => Sequence::next('INV'), 'supplier_ref' => $ref, 'purchase_order_id' => $po->id, 'supplier_id' => $po->supplier_id, 'submitted_by' => $user->id, 'currency' => $po->currency,
                'total_cents' => array_sum(array_column($rows, 'total_cents')), 'issued_on' => $data['issued_on'], 'due_on' => ($data['due_on'] ?? null) ?: null]);
            $inv->items()->createMany($rows);
            $issues = $this->issues($inv->fresh());
            $inv->update(['status' => $issues ? 'disputed' : 'matched', 'issues' => $issues ?: null]);
            if ($po->status === 'delivered') {
                $po->update(['status' => 'invoiced', 'invoiced_at' => now()]);
            }
            Notifier::send($po->buyer, "{$po->supplier->name} sent invoice {$ref} on {$po->number}: " . ($issues ? 'it does not match the order and receipts.' : 'it matches the order and receipts.'), route('invoices.show', $inv), 'Invoice');
            Audit::log('Invoice', "Invoice {$inv->number} received, " . ($issues ? count($issues) . ' difference(s) found' : 'matched'), $po, $po->number, supplierId: $po->supplier_id);

            return $inv->fresh();
        });
    }

    /** The three-way match. Each difference is one plain sentence. @return array<int, string> */
    public function issues(Invoice $inv): array
    {
        $po = $inv->order()->with('items')->first();
        $received = $this->receipts->received($po);
        $before = $this->invoiced($po, $inv->id);
        $tolerance = (int) config('procurement.invoice_price_tolerance_cents', 0);
        $out = [];
        foreach ($inv->items as $line) {
            $item = $po->items->firstWhere('id', $line->purchase_order_item_id);
            if (! $item) {
                $out[] = 'A line on the invoice is not on the order.';

                continue;
            }
            if (abs($line->unit_price_cents - $item->unit_price_cents) > $tolerance) {
                $out[] = "\"{$item->name}\": invoiced at " . Money::format($line->unit_price_cents, $inv->currency) . ', the order says ' . Money::format($item->unit_price_cents, $inv->currency) . '.';
            }
            $total = ($before[$item->id] ?? 0) + $line->quantity_milli;
            if ($total > $item->quantity_milli) {
                $out[] = "\"{$item->name}\": " . Money::qty($total) . " {$item->unit} invoiced in total, only " . Money::qty($item->quantity_milli) . ' ordered.';
            } elseif ($total > ($received[$item->id] ?? 0)) {
                $out[] = "\"{$item->name}\": " . Money::qty($total) . " {$item->unit} invoiced in total, only " . Money::qty($received[$item->id] ?? 0) . ' received so far.';
            }
        }

        return $out;
    }

    /** Run the match again, for example after a new receipt was recorded. */
    public function rematch(Invoice $inv): Invoice
    {
        if (! in_array($inv->status, ['matched', 'disputed'], true)) {
            throw new BusinessRule('Only an invoice that is still being checked can be matched again.');
        }
        $issues = $this->issues($inv);
        $inv->update(['status' => $issues ? 'disputed' : 'matched', 'issues' => $issues ?: null]);

        return $inv;
    }

    public function approve(Invoice $inv, User $user, ?string $note = null): Invoice
    {
        $this->assertBuyer($inv, $user);
        if (! in_array($inv->status, ['matched', 'disputed'], true)) {
            throw new BusinessRule('This invoice was already decided.');
        }
        $note = trim((string) $note);
        if ($inv->status === 'disputed' && mb_strlen($note) < 15) {
            throw new BusinessRule('This invoice does not match. To approve it anyway, explain why in at least 15 characters.');
        }
        $inv->update(['status' => 'approved', 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note ?: null]);
        Notifier::supplier($inv->supplier_id, "Invoice {$inv->supplier_ref} is approved for payment.", route('invoices.show', $inv), 'Invoice');
        Audit::log('Invoice', "Approved invoice {$inv->number} for payment" . ($note ? ' with a note' : ''), $inv->order, $inv->order->number, supplierId: $inv->supplier_id);

        return $inv;
    }

    public function reject(Invoice $inv, User $user, string $reason): Invoice
    {
        $this->assertBuyer($inv, $user);
        if (! in_array($inv->status, ['matched', 'disputed'], true)) {
            throw new BusinessRule('This invoice was already decided.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new BusinessRule('Tell the supplier why, in at least 10 characters.');
        }
        $inv->update(['status' => 'rejected', 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => trim($reason)]);
        Notifier::supplier($inv->supplier_id, "Invoice {$inv->supplier_ref} was rejected: " . trim($reason), route('invoices.show', $inv), 'Invoice');
        Audit::log('Invoice', "Rejected invoice {$inv->number}", $inv->order, $inv->order->number, supplierId: $inv->supplier_id);

        return $inv;
    }

    public function markPaid(Invoice $inv, User $user): Invoice
    {
        $this->assertBuyer($inv, $user);
        if ($inv->status !== 'approved') {
            throw new BusinessRule('Only an approved invoice can be marked as paid.');
        }
        $inv->update(['status' => 'paid', 'paid_at' => now()]);
        Notifier::supplier($inv->supplier_id, "Invoice {$inv->supplier_ref} was paid.", route('invoices.show', $inv), 'Invoice');
        Audit::log('Invoice', "Marked invoice {$inv->number} as paid", $inv->order, $inv->order->number, supplierId: $inv->supplier_id);

        return $inv;
    }

    private function assertBuyer(Invoice $inv, User $user): void
    {
        if (! $user->isSourcing() || ! ($user->isAdmin() || $inv->order->buyer_id === $user->id)) {
            throw new BusinessRule('Only the buyer of this order can decide on its invoices.');
        }
    }
}
