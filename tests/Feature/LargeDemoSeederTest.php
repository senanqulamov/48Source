<?php

namespace Tests\Feature;

use Database\Seeders\LargeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class LargeDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    #[RunInSeparateProcess]
    public function test_large_demo_data_uses_invented_names_and_keeps_its_workflow_references_valid(): void
    {
        app(LargeDemoSeeder::class)->run(seed: 48);

        foreach (['suppliers' => 'name', 'rfqs' => 'title', 'requisitions' => 'title', 'contracts' => 'title', 'products' => 'name', 'markets' => 'name'] as $table => $column) {
            $labels = DB::table($table)->pluck($column);

            $this->assertFalse($labels->contains(fn ($label) => str_starts_with((string) $label, 'Demo ')), "{$table} retains a Demo display name.");
            $this->assertFalse($labels->contains(fn ($label) => preg_match('/\\s\\d+$/', (string) $label) === 1), "{$table} retains a counter suffix.");
        }

        $this->assertSame(40, DB::table('categories')->where('slug', 'like', 'catalog-%')->whereNotNull('parent_id')->count());
        $this->assertSame(0, DB::table('rfqs')->where('status', 'awarded')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('quotes')->whereColumn('quotes.rfq_id', 'rfqs.id'))->count());
        $this->assertSame(0, DB::table('invoices')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('purchase_orders')->whereColumn('purchase_orders.id', 'invoices.purchase_order_id'))->count());
        $this->assertSame(0, DB::table('goods_receipts')->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')->whereNull('purchase_orders.issued_at')->count());
        $this->assertSame(0, DB::table('supplier_scorecards')->select('supplier_id', 'period')->groupBy('supplier_id', 'period')->havingRaw('COUNT(*) > 1')->count());
    }
}
