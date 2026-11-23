<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Rfqs\Form;
use App\Livewire\Rfqs\Index;
use App\Livewire\Rfqs\Show;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Notifications\SystemNotice;
use App\Services\RfqService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RfqTest extends TestCase
{
    private function fill($test, array $supplierIds)
    {
        return $test->set('f.title', 'Rebar for Substation 2')->set('f.budget', '250000.50')->set('f.deadline_at', now()->addDays(5)->format('Y-m-d\TH:i'))
            ->set('items', [['name' => 'Rebar 16 mm', 'quantity' => '144', 'unit' => 't', 'target_price' => '700'], ['name' => 'Mesh A393', 'quantity' => '12.5', 'unit' => 'pcs', 'target_price' => '']])
            ->set('suppliers', array_map('strval', $supplierIds));
    }

    public function test_a_buyer_saves_a_draft_through_the_wizard(): void
    {
        $buyer = $this->buyer();
        $s = Supplier::factory()->create();
        $this->fill(Livewire::actingAs($buyer)->test(Form::class), [$s->id])->call('saveDraft')->assertHasNoErrors();
        $rfq = Rfq::firstOrFail();
        $this->assertSame('draft', $rfq->status);
        $this->assertMatchesRegularExpression('/^RFQ-\d{4}-0001$/', $rfq->number);
        $this->assertSame(25000050, $rfq->budget_cents);
        $this->assertSame([144000, 12500], $rfq->items->pluck('quantity_milli')->all());
        $this->assertSame(70000, $rfq->items[0]->target_price_cents);
        $this->assertSame(1, $rfq->invitations()->count());
    }

    public function test_publishing_checks_each_step_and_notifies_invited_suppliers(): void
    {
        Notification::fake();
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        Livewire::actingAs($buyer)->test(Form::class)->set('f.title', 'No')->call('go', 2)->assertHasErrors('f.title')->assertSet('step', 1);
        $this->fill(Livewire::actingAs($buyer)->test(Form::class), [])->call('publish')->assertHasErrors('suppliers');
        $this->fill(Livewire::actingAs($buyer)->test(Form::class), [$su->supplier_id])->set('items.0.quantity', '0')->call('publish')->assertHasErrors('items.0.quantity');
        $this->assertSame(0, Rfq::count());
        $this->fill(Livewire::actingAs($buyer)->test(Form::class), [$su->supplier_id])->call('publish')->assertHasNoErrors();
        $rfq = Rfq::firstOrFail();
        $this->assertSame('open', $rfq->status);
        $this->assertNotNull($rfq->published_at);
        Notification::assertSentTo($su, SystemNotice::class);
    }

    public function test_only_active_suppliers_can_be_invited(): void
    {
        $buyer = $this->buyer();
        $ok = Supplier::factory()->create();
        $blocked = Supplier::factory()->create(['status' => 'blocked']);
        $this->fill(Livewire::actingAs($buyer)->test(Form::class), [$ok->id, $blocked->id])->call('saveDraft');
        $this->assertSame([$ok->id], Rfq::firstOrFail()->invitations()->pluck('supplier_id')->all());
    }

    public function test_a_published_request_cannot_be_edited(): void
    {
        $buyer = $this->buyer();
        $rfq = $this->openRfq($buyer, []);
        $this->actingAs($buyer)->get(route('rfqs.edit', $rfq))->assertForbidden();
        $this->expectException(BusinessRule::class);
        app(RfqService::class)->save($buyer, ['title' => 'Changed', 'items' => [], 'suppliers' => []], $rfq);
    }

    public function test_closing_and_cancelling(): void
    {
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$su]);
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $rfq->number])->call('closeQuoting');
        $this->assertSame('closed', $rfq->refresh()->status);
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $rfq->number])->set('reason', 'no')->call('cancel');
        $this->assertSame('closed', $rfq->refresh()->status, 'A reason is required');
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $rfq->number])->set('reason', 'Project postponed to next year')->call('cancel');
        $this->assertSame('cancelled', $rfq->refresh()->status);
        $this->assertSame('Project postponed to next year', $rfq->cancel_reason);
    }

    public function test_a_buyer_sees_only_their_own_requests_and_an_admin_sees_all(): void
    {
        $a = $this->buyer();
        $b = $this->buyer();
        $mine = $this->openRfq($a, []);
        $theirs = $this->openRfq($b, []);
        $this->actingAs($a)->get(route('rfqs.show', $mine))->assertOk();
        $this->actingAs($a)->get(route('rfqs.show', $theirs))->assertNotFound();
        $this->actingAs($a)->get(route('rfqs.compare', $theirs))->assertNotFound();
        Livewire::actingAs($a)->test(Index::class)->assertSee($mine->number)->assertDontSee($theirs->number);
        Livewire::actingAs($this->admin())->test(Index::class)->assertSee($mine->number)->assertSee($theirs->number);
    }

    public function test_bulk_close_touches_only_the_users_own_open_requests(): void
    {
        $a = $this->buyer();
        $mine = $this->openRfq($a, []);
        $theirs = $this->openRfq($this->buyer(), []);
        Livewire::actingAs($a)->test(Index::class)->set('selected', [$mine->number, $theirs->number])->call('closeSelected');
        $this->assertSame('closed', $mine->refresh()->status);
        $this->assertSame('open', $theirs->refresh()->status);
    }

    public function test_requests_close_automatically_at_the_deadline(): void
    {
        $rfq = $this->openRfq($this->buyer(), []);
        $this->assertSame(0, app(RfqService::class)->closeExpired());
        $this->travel(8)->days();
        $this->assertSame(1, app(RfqService::class)->closeExpired());
        $this->assertSame('closed', $rfq->refresh()->status);
    }

    public function test_suppliers_cannot_open_the_buyer_side_pages(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        $this->actingAs($su)->get(route('rfqs.index'))->assertForbidden();
        $this->actingAs($su)->get(route('rfqs.show', $rfq))->assertForbidden();
        $this->actingAs($su)->get(route('rfqs.create'))->assertForbidden();
        $this->actingAs($su)->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs($su)->get(route('users.index'))->assertForbidden();
    }
}
