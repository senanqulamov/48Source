<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Quotes\Form;
use App\Livewire\Quotes\Invitations;
use App\Models\Quote;
use App\Models\Supplier;
use App\Notifications\SystemNotice;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    public function test_a_supplier_accepts_then_declines_an_invitation(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        Livewire::actingAs($su)->test(Invitations::class)->assertSee($rfq->number)->call('accept', $rfq->number);
        $this->assertDatabaseHas('rfq_invitations', ['rfq_id' => $rfq->id, 'supplier_id' => $su->supplier_id, 'status' => 'accepted']);
        Livewire::actingAs($su)->test(Invitations::class)->call('askDecline', $rfq->number)->set('reason', 'No capacity this month')->call('decline');
        $this->assertDatabaseHas('rfq_invitations', ['rfq_id' => $rfq->id, 'status' => 'declined', 'decline_reason' => 'No capacity this month']);
    }

    public function test_submitting_a_quotation_computes_exact_totals_and_notifies_the_buyer(): void
    {
        Notification::fake();
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$su]); // 10 pcs and 2.5 t
        $ids = $rfq->items->pluck('id');
        Livewire::actingAs($su)->test(Form::class, ['number' => $rfq->number])
            ->set("prices.{$ids[0]}", '19.99')->set("prices.{$ids[1]}", '6.99')->set('delivery_days', '14')->set('valid_until', now()->addDays(20)->toDateString())->call('submit')->assertRedirect();
        $q = Quote::with('items')->firstOrFail();
        $this->assertSame('submitted', $q->status);
        $this->assertSame(1, $q->revision);
        $this->assertSame(19990, $q->items->firstWhere('rfq_item_id', $ids[0])->total_cents); // 19.99 x 10
        $this->assertSame(1748, $q->items->firstWhere('rfq_item_id', $ids[1])->total_cents);   // 6.99 x 2.5 = 17.475 -> 17.48
        $this->assertSame(21738, $q->total_cents);
        $this->assertDatabaseHas('rfq_invitations', ['rfq_id' => $rfq->id, 'status' => 'accepted']);
        Notification::assertSentTo($buyer, SystemNotice::class);
    }

    public function test_a_partial_quotation_is_allowed_but_an_empty_one_is_not(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        $q = $this->quote($rfq, $su, ['10', null]);
        $this->assertSame(1, $q->load('items')->coverage());
        $this->assertSame(10000, $q->total_cents);
        $this->expectException(BusinessRule::class);
        $this->quote($rfq, $this->supplierUserInvited($rfq), [null, null]);
    }

    private function supplierUserInvited($rfq)
    {
        $su = $this->supplierUser();
        $rfq->invitations()->create(['supplier_id' => $su->supplier_id]);

        return $su;
    }

    public function test_revising_keeps_one_quotation_and_raises_the_revision(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        $this->quote($rfq, $su, ['10', '20']);
        $q = $this->quote($rfq, $su, ['9', '20']);
        $this->assertSame(1, Quote::count());
        $this->assertSame(2, $q->revision);
        $this->assertSame(9000 + 5000, $q->total_cents);
    }

    public function test_withdrawing_and_quoting_again(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        $this->quote($rfq, $su, ['10', '20']);
        Livewire::actingAs($su)->test(Invitations::class)->call('withdraw', $rfq->number);
        $this->assertSame('withdrawn', Quote::firstOrFail()->status);
        $this->assertSame(0, $rfq->liveQuotes()->count());
        $this->quote($rfq, $su, ['11', '21']);
        $this->assertSame('submitted', Quote::firstOrFail()->status);
    }

    public function test_bad_prices_and_terms_are_refused(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        foreach ([[['abc', '1'], []], [['-5', '1'], []], [['0', '1'], []], [['1', '1'], ['delivery_days' => 0]], [['1', '1'], ['valid_until' => now()->subDay()->toDateString()]], [['1', '1'], ['currency' => 'XXX']]] as [$prices, $data]) {
            try {
                $this->quote($rfq, $su, $prices, $data);
                $this->fail('Accepted '.json_encode([$prices, $data]));
            } catch (BusinessRule) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, Quote::count());
    }

    public function test_only_active_invited_suppliers_can_quote_and_only_before_the_deadline(): void
    {
        $buyer = $this->buyer();
        $invited = $this->supplierUser();
        $outsider = $this->supplierUser();
        $pending = $this->supplierUser(['status' => 'pending']);
        $rfq = $this->openRfq($buyer, [$invited, $pending]);
        foreach ([$outsider, $pending] as $who) {
            try {
                $this->quote($rfq, $who, ['1', '1']);
                $this->fail('Should be refused');
            } catch (BusinessRule) {
                $this->addToAssertionCount(1);
            }
        }
        // an uninvited supplier cannot even open the request
        $this->actingAs($outsider)->get(route('quotes.form', $rfq))->assertNotFound();
        Livewire::actingAs($outsider)->test(Invitations::class)->assertDontSee($rfq->number);
        $this->travel(8)->days();
        $this->expectException(BusinessRule::class);
        $this->quote($rfq, $invited, ['1', '1']);
    }

    public function test_any_active_supplier_can_join_a_public_tender(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [], ['type' => 'public']);
        $this->actingAs($su)->get(route('quotes.form', $rfq))->assertOk();
        $this->quote($rfq, $su, ['5', '5']);
        $this->assertDatabaseHas('rfq_invitations', ['rfq_id' => $rfq->id, 'supplier_id' => $su->supplier_id, 'status' => 'accepted']);
    }

    public function test_a_supplier_never_sees_a_competitors_quotation(): void
    {
        $a = $this->supplierUser();
        $b = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$a, $b]);
        $this->quote($rfq, $a, ['123.45', '678.90']);
        Livewire::actingAs($b)->test(Form::class, ['number' => $rfq->number])->assertDontSee('123.45')->assertDontSee(Supplier::find($a->supplier_id)->name);
        Livewire::actingAs($b)->test(Invitations::class)->assertDontSee('1,234.50');
        $this->assertNull(Quote::where('supplier_id', $b->supplier_id)->first());
    }

    public function test_a_declined_invitation_blocks_quoting_until_rejoined(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        app(QuoteService::class)->respond($rfq, $su->supplier, false, 'Busy');
        try {
            $this->quote($rfq, $su, ['1', '1']);
            $this->fail('Should be refused');
        } catch (BusinessRule) {
            $this->addToAssertionCount(1);
        }
        app(QuoteService::class)->respond($rfq, $su->supplier, true);
        $this->assertSame('submitted', $this->quote($rfq, $su, ['1', '1'])->status);
    }
}
