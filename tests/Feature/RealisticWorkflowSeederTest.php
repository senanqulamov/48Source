<?php

namespace Tests\Feature;

use App\Support\Money;
use Database\Seeders\RealisticWorkflowSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RealisticWorkflowSeederTest extends TestCase
{
    public function test_synthetic_workflows_have_matching_lines_totals_and_chronological_stages(): void
    {
        app(RealisticWorkflowSeeder::class)->run();

        $this->assertSame(72, DB::table('rfqs')->where('number', 'like', 'RFQ-%-W%')->count());
        $this->assertGreaterThan(72, DB::table('workflow_fixture_records')->where('batch', 'workflow-fixtures-v1')->count());
        $this->assertSame(8, DB::table('suppliers')->where('email', 'like', 'workflow.supplier.%@example.test')->count());
        $this->assertSame(3, DB::table('users')->where('email', 'like', 'workflow.buyer.%@example.test')->count());
        $this->assertGreaterThan(20, DB::table('purchase_orders')->where('number', 'like', 'PO-%-W%')->count());
        $this->assertGreaterThan(0, DB::table('purchase_orders')->where('status', 'draft')->where('number', 'like', 'PO-%-W%')->count());

        $rfqs = DB::table('rfqs')->where('number', 'like', 'RFQ-%-W%')->get();
        $this->assertGreaterThan(8, $rfqs->pluck('created_at')->map(fn ($date) => substr($date, 0, 7))->unique()->count());
        $this->assertGreaterThan(0, $rfqs->where('status', 'open')->count());
        $this->assertGreaterThan(0, $rfqs->where('status', 'closed')->count());
        $this->assertGreaterThan(0, $rfqs->where('status', 'awarded')->count());

        foreach ($rfqs as $rfq) {
            $this->assertSame('AZN', $rfq->currency);
            $this->assertLessThanOrEqual(now()->timestamp, strtotime($rfq->created_at));
            $items = DB::table('rfq_items')->where('rfq_id', $rfq->id)->get();
            $this->assertCount(3, $items);

            $quotes = DB::table('quotes')->where('rfq_id', $rfq->id)->get();
            foreach ($quotes as $quote) {
                $lines = DB::table('quote_items')->where('quote_id', $quote->id)->get();
                $this->assertCount($items->count(), $lines);
                $this->assertSame((int) $quote->total_cents, (int) $lines->sum('total_cents'));
                $this->assertSame($rfq->currency, $quote->currency);
                $this->assertTrue(DB::table('rfq_invitations')
                    ->where('rfq_id', $rfq->id)->where('supplier_id', $quote->supplier_id)->exists());
                foreach ($lines as $line) {
                    $rfqItem = $items->firstWhere('id', $line->rfq_item_id);
                    $this->assertNotNull($rfqItem);
                    $this->assertSame(
                        Money::lineTotal((int) $line->unit_price_cents, (int) $rfqItem->quantity_milli),
                        (int) $line->total_cents
                    );
                }
            }
        }

        foreach (DB::table('purchase_orders')->where('number', 'like', 'PO-%-W%')->get() as $po) {
            $rfq = $rfqs->firstWhere('id', $po->rfq_id);
            $quote = DB::table('quotes')->find($po->quote_id);
            $this->assertSame('awarded', $rfq->status);
            $this->assertSame('won', $quote->status);
            $this->assertSame((int) $quote->total_cents, (int) $po->total_cents);
            $this->assertSame((int) $quote->supplier_id, (int) $po->supplier_id);
            $this->assertSame((int) $rfq->buyer_id, (int) $po->buyer_id);
            $this->assertSame((int) $po->total_cents, (int) DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->sum('total_cents'));
            $this->assertGreaterThanOrEqual(strtotime($po->created_at), strtotime($po->issued_at ?? $po->created_at));
            if ($po->delivered_at !== null) {
                $this->assertNotNull($po->shipped_at);
                $this->assertGreaterThanOrEqual(strtotime($po->shipped_at), strtotime($po->delivered_at));
                $receipt = DB::table('goods_receipts')->where('purchase_order_id', $po->id)->first();
                $this->assertNotNull($receipt);
                $this->assertSame(3, DB::table('goods_receipt_items')->where('goods_receipt_id', $receipt->id)->count());
            }
            if ($po->status === 'invoiced') {
                $invoice = DB::table('invoices')->where('purchase_order_id', $po->id)->first();
                $this->assertNotNull($invoice);
                $this->assertSame((int) $po->total_cents, (int) $invoice->total_cents);
                $this->assertSame((int) $invoice->total_cents, (int) DB::table('invoice_items')->where('invoice_id', $invoice->id)->sum('total_cents'));
            }
        }

        app(RealisticWorkflowSeeder::class)->run();
        $this->assertSame(72, DB::table('rfqs')->where('number', 'like', 'RFQ-%-W%')->count());
    }
}