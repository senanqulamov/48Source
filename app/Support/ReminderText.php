<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Reminder;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\SupplierDocument;
use Illuminate\Support\Collection;

/** Turns scheduler-only reminder keys into safe, human-readable history rows. */
final class ReminderText
{
    /** @return array<int, array{kind:string,title:string,ref:?string,to:string,url:?string,icon:string,tone:string}> */
    public static function describe(Collection $reminders): array
    {
        $parsed = $reminders->mapWithKeys(function (Reminder $reminder) {
            $parts = explode(':', $reminder->key);

            return [$reminder->id => [$parts[0] ?? '', (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0)]];
        });
        $ids = fn (string ...$types) => $parsed->filter(fn ($p) => in_array($p[0], $types, true))->pluck(1)->filter()->unique()->all();
        $user = auth()->user();
        $rfqs = Rfq::visibleTo($user)->with('invitations.supplier')->whereKey($ids('rfq-closing'))->get()->keyBy('id');
        $orders = PurchaseOrder::visibleTo($user)->with('supplier')->whereKey($ids('po-ack', 'po-late'))->get()->keyBy('id');
        $invoices = Invoice::visibleTo($user)->with('order.buyer')->whereKey($ids('inv-due'))->get()->keyBy('id');
        $requisitions = Requisition::visibleTo($user)->whereKey($ids('pr-wait'))->get()->keyBy('id');
        $documents = SupplierDocument::with('supplier')->whereKey($ids('doc-exp'))->get()->keyBy('id');

        $result = $reminders->mapWithKeys(function (Reminder $reminder) use ($parsed, $rfqs, $orders, $invoices, $requisitions, $documents) {
            [$type, $id, $extra] = $parsed[$reminder->id];
            $other = ['kind' => 'other', 'title' => 'Reminder', 'ref' => null, 'to' => '', 'url' => null, 'icon' => 'bell', 'tone' => ''];
            if ($type === 'rfq-closing' && ($r = $rfqs->get($id))) {
                $supplier = $r->invitations->firstWhere('supplier_id', $extra)?->supplier;

                return [$reminder->id => ['kind' => 'rfq', 'title' => 'Quotation deadline reminder', 'ref' => $r->number, 'to' => $supplier?->name ?? 'the supplier', 'url' => route('rfqs.show', $r), 'icon' => 'file', 'tone' => 's']];
            }
            if (in_array($type, ['po-ack', 'po-late'], true) && ($p = $orders->get($id))) {
                return [$reminder->id => ['kind' => 'po', 'title' => $type === 'po-ack' ? 'Order waiting for acknowledgement' : 'Delivery date passed', 'ref' => $p->number, 'to' => $type === 'po-ack' ? $p->supplier->name : 'the buyer and '.$p->supplier->name, 'url' => route('orders.show', $p), 'icon' => 'box', 'tone' => 'o']];
            }
            if ($type === 'inv-due' && ($i = $invoices->get($id))) {
                return [$reminder->id => ['kind' => 'inv', 'title' => 'Invoice due soon', 'ref' => $i->number, 'to' => $i->order->buyer->name, 'url' => route('invoices.show', $i), 'icon' => 'file', 'tone' => 'i']];
            }
            if ($type === 'pr-wait' && ($r = $requisitions->get($id))) {
                return [$reminder->id => ['kind' => 'pr', 'title' => 'Requisition waiting for approval', 'ref' => $r->number, 'to' => 'the approvers', 'url' => route('requisitions.show', $r), 'icon' => 'check', 'tone' => '']];
            }
            if ($type === 'doc-exp' && ($d = $documents->get($id))) {
                return [$reminder->id => ['kind' => 'doc', 'title' => 'Supplier document expiring', 'ref' => null, 'to' => $d->supplier->name, 'url' => route('suppliers.show', $d->supplier), 'icon' => 'file', 'tone' => 's']];
            }

            return [$reminder->id => $other];
        })->all();
        foreach ($reminders as $reminder) {
            $result[$reminder->id]['created_at'] = $reminder->created_at;
        }

        return $result;
    }
}
