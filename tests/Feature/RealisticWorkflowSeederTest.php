<?php

namespace Tests\Feature;

use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RealisticWorkflowSeederTest extends TestCase
{
    public function test_full_pipeline_creates_connected_products_accounts_and_business_workflows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(130, DB::table('suppliers')->count());
        $this->assertSame(105, DB::table('users')->where('role','buyer')->count());
        $this->assertSame(105, DB::table('users')->where('role','seller')->count());
        $this->assertSame(105, DB::table('markets')->count());
        $this->assertSame(105, DB::table('stores')->count());
        $this->assertSame(240, DB::table('rfqs')->count());
        $this->assertSame(240, DB::table('requisitions')->count());
        $this->assertSame(count(\App\Support\Perm::ROLES)*count(\App\Support\Perm::MODULES),
            DB::table('role_permissions')->count());
        $this->assertGreaterThan(0, DB::table('sequences')->count());
        $this->assertGreaterThan(280, DB::table('products')->count());
        $this->assertGreaterThan(100, DB::table('purchase_orders')->count());
        $this->assertGreaterThan(100, DB::table('market_orders')->count());

        $offers = DB::table('offers')->selectRaw('store_id, count(*) as n')->groupBy('store_id')->get();
        $this->assertCount(105, $offers);
        foreach ($offers as $offer) {
            $this->assertGreaterThanOrEqual(5, $offer->n);
            $this->assertLessThanOrEqual(10, $offer->n);
        }
        foreach (['quotes','quote_items','rfq_invitations','purchase_order_items','invoices',
            'invoice_items','goods_receipts','goods_receipt_items','requisition_items',
            'requisition_approvals','contracts','contract_items','contract_releases',
            'contract_release_items','supplier_documents','supplier_scorecards',
            'field_assessments','rfis','rfi_responses','rfq_questions','auctions',
            'auction_bids','bafo_rounds','conversations','messages','conversation_reads',
            'activity_logs','notifications','attachments','outbox_emails','worker_tasks',
            'market_order_items','cart_items','budgets'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table.' was not populated');
        }

        foreach (DB::table('rfqs')->get() as $rfq) {
            $this->assertSame(3, DB::table('rfq_items')->where('rfq_id',$rfq->id)->count());
            $requisition = DB::table('requisitions')->where('rfq_id',$rfq->id)->first();
            $this->assertNotNull($requisition);
            $this->assertSame((int)$rfq->budget_cents, (int)$requisition->total_cents);
            $this->assertSame(3, DB::table('requisition_items')->where('requisition_id',$requisition->id)->count());
            foreach (DB::table('quotes')->where('rfq_id',$rfq->id)->get() as $quote) {
                $lines = DB::table('quote_items')->where('quote_id',$quote->id)->get();
                $this->assertCount(3,$lines);
                $this->assertSame((int)$quote->total_cents,(int)$lines->sum('total_cents'));
                $this->assertSame('AZN', $quote->currency);
                $this->assertTrue(DB::table('rfq_invitations')->where('rfq_id',$rfq->id)
                    ->where('supplier_id',$quote->supplier_id)->exists());
                foreach ($lines as $line) {
                    $item = DB::table('rfq_items')->where('id',$line->rfq_item_id)->first();
                    $this->assertSame(Money::lineTotal($line->unit_price_cents,$item->quantity_milli),(int)$line->total_cents);
                }
            }
        }

        foreach (DB::table('purchase_orders')->get() as $po) {
            $quote = DB::table('quotes')->find($po->quote_id);
            $this->assertSame((int)$po->supplier_id,(int)$quote->supplier_id);
            $this->assertSame((int)$po->rfq_id,(int)$quote->rfq_id);
            $this->assertSame((int)$po->total_cents,(int)$quote->total_cents);
            $this->assertSame((int)$po->total_cents,(int)DB::table('purchase_order_items')
                ->where('purchase_order_id',$po->id)->sum('total_cents'));
        }

        $this->assertGreaterThan(0, DB::table('market_orders')
            ->where('created_at', '>=', now()->subDays(30))->count());
        $this->assertGreaterThan(0, DB::table('market_orders')
            ->where('created_at', '<', now()->subMonths(6))->count());

        $orders = DB::table('market_orders')->get();
        foreach ($orders as $order) {
            $this->assertSame((int)$order->total_cents,(int)DB::table('market_order_items')
                ->where('market_order_id',$order->id)->sum('total_cents'));
        }

        $before = DB::table('workflow_fixture_records')->count();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, DB::table('workflow_fixture_records')->count());
        $this->assertSame(240, DB::table('rfqs')->count());
    }
}
