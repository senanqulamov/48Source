<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\RealisticWorkflowSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardCurrencyTest extends TestCase
{
    public function test_dashboard_uses_azn_for_workflow_fixture_and_never_sums_mixed_currencies(): void
    {
        $this->seed(RealisticWorkflowSeeder::class);
        $buyer = User::where('email', 'workflow.buyer.0@example.test')->firstOrFail();

        $this->actingAs($buyer)->get('/')->assertOk()->assertSee('₼');
        $order = PurchaseOrder::where('buyer_id', $buyer->id)
            ->where('status', '!=', 'cancelled')->firstOrFail();
        DB::table('purchase_orders')->where('id', $order->id)->update(['currency' => 'EUR']);

        $this->actingAs($buyer)->get('/')->assertOk()
            ->assertSee('Mixed currencies: amounts cannot be totaled');
    }
}
