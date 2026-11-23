<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MarketOrder;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Printable documents. Every method uses the same visibility rules as the page it belongs to. */
class PdfController extends Controller
{
    private function render(string $file, array $doc): Response
    {
        return Pdf::loadView('pdf.document', ['doc' => $doc + ['org' => config('app.org', config('app.name')), 'printed' => now()->format('j M Y, H:i')]])->setPaper('a4')->stream($file . '.pdf');
    }

    public function order(Request $request, string $number): Response
    {
        $po = PurchaseOrder::visibleTo($request->user())->where('number', $number)->with('items', 'supplier', 'buyer', 'rfq')->firstOrFail();

        return $this->render($po->number, [
            'title' => 'Purchase order', 'number' => $po->number, 'status' => $po->statusLabel(),
            'left' => ['Supplier', [$po->supplier->name, $po->supplier->city, $po->supplier->email]], 'right' => ['Buyer', [$po->buyer->name, $po->buyer->email]],
            'facts' => ['Order date' => $po->created_at->format('j M Y'), 'Related request' => $po->rfq->number, 'Delivery due' => $po->expected_at?->format('j M Y') ?? '-', 'Deliver to' => $po->delivery_location ?: '-', 'Payment terms' => $po->payment_terms ?: '-', 'Currency' => $po->currency],
            'head' => ['Item', 'Quantity', 'Unit price', 'Total'],
            'rows' => $po->items->map(fn ($i) => [$i->name, Money::qty($i->quantity_milli) . ' ' . $i->unit, Money::format($i->unit_price_cents, $po->currency), Money::format($i->total_cents, $po->currency)])->all(),
            'total' => ['Order total', Money::format($po->total_cents, $po->currency)],
        ]);
    }

    public function rfq(Request $request, string $number): Response
    {
        $rfq = Rfq::visibleTo($request->user())->where('number', $number)->with('items', 'buyer', 'category')->firstOrFail();
        abort_if($rfq->status === 'draft' && ! $request->user()->isSourcing(), 404);

        return $this->render($rfq->number, [
            'title' => 'Request for quotation', 'number' => $rfq->number, 'status' => $rfq->statusLabel(), 'subject' => $rfq->title,
            'left' => ['Buyer', [$rfq->buyer->name, $rfq->buyer->email]], 'right' => ['Category', [$rfq->category?->name ?? 'No category', Rfq::TYPES[$rfq->type] ?? $rfq->type]],
            'facts' => ['Quotation deadline' => $rfq->deadline_at?->format('j M Y, H:i') ?? '-', 'Delivery needed by' => $rfq->required_by?->format('j M Y') ?? '-', 'Delivery location' => $rfq->delivery_location ?: '-', 'Payment terms' => $rfq->payment_terms ?: '-', 'Currency' => $rfq->currency],
            'text' => array_filter(['Description' => $rfq->description, 'Requirements' => $rfq->requirements]),
            'head' => ['#', 'Item', 'Quantity', 'Unit'],
            'rows' => $rfq->items->values()->map(fn ($i, $k) => [$k + 1, $i->name, $i->qty(), $i->unit])->all(),
        ]);
    }

    public function invoice(Request $request, string $number): Response
    {
        $inv = Invoice::visibleTo($request->user())->where('number', $number)->with('items.orderItem', 'supplier', 'order.buyer')->firstOrFail();

        return $this->render($inv->number, [
            'title' => 'Invoice record', 'number' => $inv->number, 'status' => $inv->statusLabel(),
            'left' => ['Supplier', [$inv->supplier->name, $inv->supplier->city, 'Invoice no. ' . $inv->supplier_ref]], 'right' => ['Buyer', [$inv->order->buyer->name, 'Order ' . $inv->order->number]],
            'facts' => ['Invoice date' => $inv->issued_on->format('j M Y'), 'Due' => $inv->due_on?->format('j M Y') ?? '-', 'Three-way match' => $inv->issues ? count($inv->issues) . ' difference(s)' : 'Matches', 'Paid' => $inv->paid_at?->format('j M Y') ?? 'Not yet'],
            'text' => $inv->issues ? ['Differences' => implode("\n", $inv->issues)] : [],
            'head' => ['Item', 'Quantity', 'Unit price', 'Total'],
            'rows' => $inv->items->map(fn ($l) => [$l->orderItem->name, Money::qty($l->quantity_milli) . ' ' . $l->orderItem->unit, Money::format($l->unit_price_cents, $inv->currency), Money::format($l->total_cents, $inv->currency)])->all(),
            'total' => ['Invoice total', Money::format($inv->total_cents, $inv->currency)],
        ]);
    }

    public function marketOrder(Request $request, string $number): Response
    {
        $o = MarketOrder::visibleTo($request->user())->where('number', $number)->with('items', 'store.market', 'buyer', 'supplier')->firstOrFail();

        return $this->render($o->number, [
            'title' => 'Marketplace order', 'number' => $o->number, 'status' => $o->statusLabel(),
            'left' => ['Store', [$o->store->name, $o->store->market->name, $o->store->phone]], 'right' => ['Buyer', [$o->supplier?->name ?? $o->buyer->name, $o->buyer->name, $o->buyer->email]],
            'facts' => ['Placed' => $o->created_at->format('j M Y, H:i'), 'Deliver to' => $o->deliver_to ?: '-', 'Note' => $o->note ?: '-', 'Currency' => $o->currency],
            'head' => ['Product', 'SKU', 'Quantity', 'Unit price', 'Total'],
            'rows' => $o->items->map(fn ($l) => [$l->name, $l->sku, Money::qty($l->quantity_milli) . ' ' . $l->unit, Money::format($l->unit_price_cents, $o->currency), Money::format($l->total_cents, $o->currency)])->all(),
            'total' => ['Order total', Money::format($o->total_cents, $o->currency)],
        ]);
    }
}
