<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Orders\Index;
use App\Livewire\Orders\Show;
use App\Models\PurchaseOrder;
use App\Services\AwardService;
use App\Services\PurchaseOrderService;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    private function order(): array
    {
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$su]);
        $q = $this->quote($rfq, $su, ['100', '200']);
        $po = app(AwardService::class)->award($this->closeRfq($rfq), $q, $buyer);

        return [$buyer, $su, $po];
    }

    public function test_the_order_moves_through_its_lifecycle_with_each_side_doing_its_own_steps(): void
    {
        [$buyer, $su, $po] = $this->order();
        $svc = app(PurchaseOrderService::class);
        $steps = [[$buyer, 'issued', 'issued_at'], [$su, 'acknowledged', 'acknowledged_at'], [$su, 'in_transit', 'shipped_at'], [$buyer, 'delivered', 'delivered_at'], [$su, 'invoiced', 'invoiced_at']];
        foreach ($steps as [$who, $status, $stamp]) {
            $other = $who->is($buyer) ? $su : $buyer;
            try {
                $svc->advance($po->refresh(), $other);
                $this->fail("The wrong side moved the order to {$status}");
            } catch (BusinessRule) {
                $this->addToAssertionCount(1);
            }
            Livewire::actingAs($who)->test(Show::class, ['number' => $po->number])->call('advance');
            $this->assertSame($status, $po->refresh()->status);
            $this->assertNotNull($po->{$stamp});
        }
        $this->assertNull($svc->nextFor($po, $buyer));
        $this->assertNull($svc->nextFor($po, $su));
    }

    public function test_a_supplier_does_not_see_a_draft_order_or_another_suppliers_order(): void
    {
        [$buyer, $su, $po] = $this->order();
        $other = $this->supplierUser();
        $this->actingAs($su)->get(route('orders.show', $po))->assertNotFound();
        Livewire::actingAs($su)->test(Index::class)->assertDontSee($po->number);
        app(PurchaseOrderService::class)->advance($po, $buyer);
        $this->actingAs($su)->get(route('orders.show', $po))->assertOk();
        $this->actingAs($other)->get(route('orders.show', $po))->assertNotFound();
        $this->actingAs($this->buyer())->get(route('orders.show', $po))->assertNotFound();
        $this->actingAs($this->admin())->get(route('orders.show', $po))->assertOk();
    }

    public function test_an_order_can_be_cancelled_only_before_it_is_acknowledged(): void
    {
        [$buyer, $su, $po] = $this->order();
        $svc = app(PurchaseOrderService::class);
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $po->number])->set('reason', 'x')->call('cancel');
        $this->assertSame('draft', $po->refresh()->status, 'A reason is required');
        $svc->advance($po, $buyer);
        $svc->advance($po->refresh(), $su);
        try {
            $svc->cancel($po->refresh(), $buyer, 'Changed our mind entirely');
            $this->fail('Cancelled after acknowledgement');
        } catch (BusinessRule) {
            $this->addToAssertionCount(1);
        }
        [$buyer2, $su2, $po2] = $this->order();
        try {
            $svc->cancel($po2, $su2, 'A supplier cannot cancel');
            $this->fail('Supplier cancelled');
        } catch (BusinessRule) {
            $this->addToAssertionCount(1);
        }
        $svc->cancel($po2, $buyer2, 'Scope removed from the project');
        $this->assertSame('cancelled', PurchaseOrder::find($po2->id)->status);
    }
}
