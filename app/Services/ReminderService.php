<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Reminder;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Support\Notifier;

/** Service-level reminders, run by the scheduler. Each reminder is sent once per record and recipient group. */
class ReminderService
{
    private int $sent = 0;

    private function once(string $key, callable $send): void
    {
        if (Reminder::where('key', $key)->exists()) {
            return;
        }
        Reminder::create(['key' => $key]);
        $send();
        $this->sent++;
    }

    public function run(): int
    {
        $this->sent = 0;
        // Requests closing soon (SLA rule, one day by default): invited suppliers that have not quoted yet.
        foreach (Rfq::where('status', 'open')->whereBetween('deadline_at', [now(), now()->addDays(\App\Support\Setting::int('sla.quote_reminder_days'))])->with('invitations', 'quotes')->get() as $rfq) {
            foreach ($rfq->invitations->where('status', '!=', 'declined') as $inv) {
                if (! $rfq->quotes->where('supplier_id', $inv->supplier_id)->where('status', 'submitted')->count()) {
                    $this->once("rfq-closing:{$rfq->id}:{$inv->supplier_id}", fn () => Notifier::supplier($inv->supplier_id, "Reminder: {$rfq->number} closes {$rfq->deadline_at->diffForHumans()} and you have not sent a quotation.", route('quotes.form', $rfq), 'Reminder'));
                }
            }
        }
        // Orders issued some days ago (SLA rule, three by default) and still not acknowledged.
        foreach (PurchaseOrder::where('status', 'issued')->where('issued_at', '<=', now()->subDays(\App\Support\Setting::int('sla.po_ack_days')))->get() as $po) {
            $this->once("po-ack:{$po->id}", fn () => Notifier::supplier($po->supplier_id, "Reminder: purchase order {$po->number} is waiting for your acknowledgement.", route('orders.show', $po), 'Reminder'));
        }
        // Orders past their delivery date.
        foreach (PurchaseOrder::whereIn('status', ['acknowledged', 'in_transit'])->whereDate('expected_at', '<', now()->toDateString())->with('buyer')->get() as $po) {
            $this->once("po-late:{$po->id}", function () use ($po) {
                Notifier::send($po->buyer, "Purchase order {$po->number} is past its delivery date.", route('orders.show', $po), 'Reminder');
                Notifier::supplier($po->supplier_id, "Purchase order {$po->number} is past its delivery date. Please update the buyer.", route('orders.show', $po), 'Reminder');
            });
        }
        // Approved invoices due within three days or overdue.
        foreach (Invoice::where('status', 'approved')->whereDate('due_on', '<=', now()->addDays(3)->toDateString())->with('order.buyer')->get() as $inv) {
            $this->once("inv-due:{$inv->id}", fn () => Notifier::send($inv->order->buyer, "Invoice {$inv->supplier_ref} is due {$inv->due_on->format('j M Y')} and not yet marked as paid.", route('invoices.show', $inv), 'Reminder'));
        }
        // Requisitions waiting for approval longer than two days.
        foreach (Requisition::where('status', 'pending')->where('submitted_at', '<=', now()->subDays(\App\Support\Setting::int('sla.requisition_days')))->with('approvals')->get() as $req) {
            $step = $req->approvals->firstWhere('status', 'pending');
            if ($step) {
                $this->once("pr-wait:{$req->id}:{$step->step}", fn () => Notifier::send(User::whereIn('role', array_unique([$step->role, 'admin']))->where('id', '!=', $req->requester_id)->get()->filter(fn (User $u) => $u->allowed('requisitions', 'A')),
                    "Reminder: requisition {$req->number} has waited too long for {$step->label}.", route('requisitions.show', $req), 'Reminder'));
            }
        }
        // Supplier documents expiring within 14 days.
        foreach (SupplierDocument::whereBetween('expires_on', [now()->toDateString(), now()->addDays(14)->toDateString()])->get() as $doc) {
            $this->once("doc-exp:{$doc->id}:{$doc->expires_on->format('Ymd')}", fn () => Notifier::supplier($doc->supplier_id, "Your document \"{$doc->title}\" expires on {$doc->expires_on->format('j M Y')}. Please upload the renewed one.", route('suppliers.show', $doc->supplier_id), 'Reminder'));
        }

        return $this->sent;
    }
}
