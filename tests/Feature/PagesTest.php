<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RealisticWorkflowSeeder;
use Tests\TestCase;

/**
 * Render every role's pages against coherent procurement fixtures, not
 * hardcoded email addresses and unrelated, arbitrary legacy demo users.
 */
class PagesTest extends TestCase
{
    public function test_every_page_renders_for_each_role(): void
    {
        $this->seed(RealisticWorkflowSeeder::class);
        auth()->logout();

        $admin = User::factory()->admin()->create()->fresh();
        $open = Rfq::where('status', 'open')->with('invitations')->firstOrFail();
        $closed = Rfq::where('status', 'closed')->firstOrFail();
        $draft = Rfq::where('status', 'draft')->firstOrFail();
        $po = PurchaseOrder::firstOrFail();

        $sourcing = [
            '/', '/rfqs', '/rfqs?status=open&q=steel', '/rfqs/create',
            "/rfqs/{$open->number}", "/rfqs/{$open->number}?tab=quotes",
            "/rfqs/{$open->number}?tab=suppliers", "/rfqs/{$open->number}?tab=items",
            "/rfqs/{$open->number}?tab=activity", "/rfqs/{$draft->number}/edit",
            "/rfqs/{$closed->number}/compare", '/compare', '/orders',
            "/orders/{$po->number}", '/suppliers', '/suppliers?status=pending',
            '/categories', '/notifications', '/settings', '/search?q=steel',
        ];
        foreach (array_merge($sourcing, ['/users', '/users?tab=roles', '/logs']) as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        $buyer = User::findOrFail($open->buyer_id);
        foreach (['/', '/rfqs', '/rfqs/create', "/rfqs/{$open->number}",
            '/compare', '/orders', '/suppliers', '/categories', '/notifications',
            '/settings', '/search?q=steel'] as $page) {
            $this->actingAs($buyer)->get($page)->assertOk();
        }

        $supplierId = $open->invitations->firstOrFail()->supplier_id;
        $supplier = User::where('role', 'supplier')->where('supplier_id', $supplierId)->firstOrFail();
        $supplierPages = ['/', '/quotes', '/quotes?tab=sent', '/quotes?tab=closed',
            "/quotes/{$open->number}", '/orders', '/notifications',
            '/settings', '/search?q=rebar'];
        $supplierClosed = Rfq::where('status', 'closed')
            ->whereHas('invitations', fn ($q) => $q->where('supplier_id', $supplierId))->first();
        if ($supplierClosed) {
            $supplierPages[] = "/quotes/{$supplierClosed->number}";
        }
        $supplierOrder = PurchaseOrder::where('supplier_id', $supplierId)->where('status', '!=', 'draft')->first();
        if ($supplierOrder) {
            $supplierPages[] = "/orders/{$supplierOrder->number}";
        }
        foreach ($supplierPages as $page) {
            $this->actingAs($supplier)->get($page)->assertOk();
        }

        $pendingCompany = Supplier::factory()->pending()->create();
        $pending = User::factory()->supplier($pendingCompany)->create()->fresh();
        $market = Market::create(['name' => 'Fixture market', 'city' => 'Bakı', 'status' => 'active']);
        $store = Store::create(['market_id' => $market->id, 'name' => 'Fixture store', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id])->fresh();
        $marketWorker = User::factory()->create(['role' => 'market_worker', 'store_id' => $store->id])->fresh();
        $fieldWorker = User::factory()->create(['role' => 'supplier_worker', 'supplier_id' => $supplierId])->fresh();

        foreach ([$pending, $seller, $marketWorker, $fieldWorker] as $account) {
            foreach (['/', '/notifications', '/settings'] as $page) {
                $this->actingAs($account)->get($page)->assertOk();
            }
        }
        $this->actingAs($pending)->get('/')->assertSee('waiting for approval');

        $otherBuyer = User::where('role', 'buyer')->whereKeyNot($buyer->id)->firstOrFail();
        $this->actingAs($otherBuyer)->get("/rfqs/{$open->number}")->assertNotFound();
    }
}
