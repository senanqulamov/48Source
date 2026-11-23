<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Contracts\Show as ContractShow;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Messages\Index as Messages;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Requisitions\Form as RequisitionForm;
use App\Livewire\Requisitions\Show as RequisitionShow;
use App\Livewire\Rfqs\Auction as AuctionPage;
use App\Models\Contract;
use App\Models\FieldAssessment;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AssessmentService;
use App\Services\AuctionService;
use App\Services\AwardService;
use App\Services\BafoService;
use App\Services\ContractService;
use App\Services\InvoiceService;
use App\Services\MessageService;
use App\Services\PurchaseOrderService;
use App\Services\QaService;
use App\Services\ReceiptService;
use App\Services\RequisitionService;
use App\Services\RfiService;
use App\Services\SupplierInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PartTwoTest extends TestCase
{
    use RefreshDatabase;

    private function refuses(callable $fn, string $needle): void
    {
        try {
            $fn();
            $this->fail("Expected a refusal containing: {$needle}");
        } catch (BusinessRule $e) {
            $this->assertStringContainsString($needle, $e->getMessage());
        }
    }

    private function requisition(User $by, string $price = '100.00'): Requisition
    {
        return app(RequisitionService::class)->save($by, ['title' => 'Site laptops', 'justification' => 'New office'], [['name' => 'Laptop', 'quantity' => '10', 'unit' => 'pcs', 'est_price' => $price]]);
    }

    /** A purchase order the supplier has acknowledged: 10 pcs at 12.50 and 2.5 t at 400.00 = 1,125.00. */
    private function acknowledgedOrder(): array
    {
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$sup]);
        $q = $this->quote($rfq, $sup, ['12.50', '400.00']);
        $rfq = $this->closeRfq($rfq);
        Auth::setUser($buyer);
        $po = app(AwardService::class)->award($rfq, $q->fresh(), $buyer);
        $orders = app(PurchaseOrderService::class);
        $orders->advance($po, $buyer);
        Auth::setUser($sup);
        $orders->advance($po->fresh(), $sup);
        $orders->advance($po->fresh(), $sup);

        return [$po->fresh()->load('items'), $buyer, $sup];
    }

    public function test_a_small_requisition_needs_one_approval_and_a_large_one_two(): void
    {
        $service = app(RequisitionService::class);
        $this->assertCount(1, $service->chainFor(100000));
        $this->assertCount(2, $service->chainFor(2500000));
        $this->assertCount(1, $service->chainFor(0));

        $buyer = $this->buyer();
        Auth::setUser($buyer);
        $req = $this->requisition($buyer);
        $this->assertSame(100000, $req->total_cents);
        $req = $service->submit($req, $buyer);
        $this->assertSame('pending', $req->status);
        $this->assertSame(1, $req->approvals()->count());
    }

    public function test_nobody_approves_their_own_requisition(): void
    {
        $service = app(RequisitionService::class);
        $buyer = $this->buyer();
        Auth::setUser($buyer);
        $req = $service->submit($this->requisition($buyer), $buyer);
        $this->assertFalse($service->canDecide($req, $buyer));
        $this->refuses(fn () => $service->decide($req, $buyer, true), 'your own');

        $other = $this->buyer();
        Auth::setUser($other);
        $this->assertTrue($service->canDecide($req, $other));
        $this->assertSame('approved', $service->decide($req, $other, true)->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $buyer->id]);
    }

    public function test_a_large_requisition_passes_both_levels_in_order_and_converts_to_an_rfq(): void
    {
        $service = app(RequisitionService::class);
        $buyer = $this->buyer();
        $other = $this->buyer();
        $admin = $this->admin();
        Auth::setUser($buyer);
        $req = $service->submit($this->requisition($buyer, '3000.00'), $buyer);
        $this->assertSame(2, $req->approvals()->count());

        Auth::setUser($other);
        $req = $service->decide($req, $other, true);
        $this->assertSame('pending', $req->status);
        $this->assertFalse($service->canDecide($req, $other), 'second level belongs to the administrator');
        $this->refuses(fn () => $service->decide($req, $other, true), 'another approver');

        Auth::setUser($admin);
        $req = $service->decide($req, $admin, true);
        $this->assertSame('approved', $req->status);

        Auth::setUser($buyer);
        $rfq = $service->convert($req, $buyer);
        $this->assertSame('draft', $rfq->status);
        $this->assertSame(3000000, $rfq->budget_cents);
        $this->assertSame('Laptop', $rfq->items()->first()->name);
        $this->assertSame('converted', $req->fresh()->status);
        $this->refuses(fn () => $service->convert($req->fresh(), $buyer), 'approved');
    }

    public function test_rejecting_needs_a_reason_and_stops_the_chain(): void
    {
        $service = app(RequisitionService::class);
        $buyer = $this->buyer();
        $other = $this->buyer();
        Auth::setUser($buyer);
        $req = $service->submit($this->requisition($buyer), $buyer);
        Auth::setUser($other);
        $this->refuses(fn () => $service->decide($req, $other, false, ''), 'why');
        $this->assertSame('rejected', $service->decide($req, $other, false, 'Budget is not available this quarter')->status);
        $this->refuses(fn () => $service->convert($req->fresh(), $other), 'approved');
    }

    public function test_requisition_pages_work_through_livewire(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        Livewire::actingAs($buyer)->test(RequisitionForm::class)->set('f.title', 'Safety boots')->set('items.0.name', 'Boots')->set('items.0.quantity', '40')->set('items.0.est_price', '55')
            ->call('save', true)->assertRedirect();
        $req = Requisition::firstOrFail();
        $this->assertSame('pending', $req->status);
        $this->assertSame(220000, $req->total_cents);
        Livewire::actingAs($buyer)->test(RequisitionShow::class, ['number' => $req->number])->assertDontSee("open('approve')", false);
        Livewire::actingAs($other)->test(RequisitionShow::class, ['number' => $req->number])->assertSee("open('approve')", false)->call('decide', true);
        $this->assertSame('approved', $req->fresh()->status);
        $this->actingAs($this->supplierUser())->get(route('requisitions.index'))->assertForbidden();
    }

    private function contract(User $buyer, Supplier $supplier, ?string $ceiling = '1000.00'): Contract
    {
        return app(ContractService::class)->save($buyer, ['supplier_id' => $supplier->id, 'title' => 'Steel framework', 'ceiling' => $ceiling, 'starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addMonths(6)->toDateString()],
            [['name' => 'Rebar 16 mm', 'unit' => 't', 'price' => '700.00']]);
    }

    public function test_release_orders_use_contract_prices_and_respect_the_ceiling(): void
    {
        $service = app(ContractService::class);
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        Auth::setUser($buyer);
        $c = $this->contract($buyer, $sup->supplier);
        $item = $c->items()->first();
        $this->refuses(fn () => $service->release($c->load('items'), $buyer, [$item->id => '1']), 'active contract');
        $c = $service->activate($c)->load('items');

        $ro = $service->release($c, $buyer, [$item->id => '1.25']);
        $this->assertSame(87500, $ro->total_cents);
        $this->assertSame(87500, $c->fresh()->released_cents);
        $this->assertSame(12500, $c->fresh()->remainingCents());
        $this->refuses(fn () => $service->release($c->fresh()->load('items'), $buyer, [$item->id => '0.5']), 'ceiling');

        // A cancelled release gives its value back.
        $service->moveRelease($ro, $buyer, 'cancelled');
        $this->assertSame(0, $c->fresh()->released_cents);
    }

    public function test_only_the_supplier_accepts_a_release_and_only_the_buyer_confirms_delivery(): void
    {
        $service = app(ContractService::class);
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $stranger = $this->supplierUser();
        Auth::setUser($buyer);
        $c = $service->activate($this->contract($buyer, $sup->supplier, null))->load('items');
        $ro = $service->release($c, $buyer, [$c->items->first()->id => '2']);
        $this->refuses(fn () => $service->moveRelease($ro, $buyer, 'accepted'), 'cannot');
        $this->refuses(fn () => $service->moveRelease($ro, $stranger, 'accepted'), 'cannot');
        Auth::setUser($sup);
        $this->assertSame('accepted', $service->moveRelease($ro, $sup, 'accepted')->status);
        $this->refuses(fn () => $service->moveRelease($ro, $sup, 'delivered'), 'cannot');
        Auth::setUser($buyer);
        $this->assertSame('delivered', $service->moveRelease($ro, $buyer, 'delivered')->status);

        $this->actingAs($stranger)->get(route('contracts.show', $c))->assertNotFound();
        Livewire::actingAs($sup)->test(ContractShow::class, ['number' => $c->number])->assertSee('Steel framework')->call('activate')->assertForbidden();
    }

    public function test_contracts_expire_and_terminate(): void
    {
        $service = app(ContractService::class);
        $buyer = $this->buyer();
        Auth::setUser($buyer);
        $c = $service->activate($this->contract($buyer, $this->supplierUser()->supplier));
        $this->refuses(fn () => $service->terminate($c, 'short'), '10 characters');
        $c->update(['ends_on' => now()->subDays(2)]);
        $this->assertSame(1, $service->expireEnded());
        $this->assertSame('expired', $c->fresh()->status);
        $this->refuses(fn () => $service->release($c->fresh()->load('items'), $buyer, [$c->items()->first()->id => '1']), 'active contract');
    }

    public function test_receipts_cannot_exceed_what_was_ordered_and_full_receipt_marks_delivery(): void
    {
        [$po, $buyer] = $this->acknowledgedOrder();
        $receipts = app(ReceiptService::class);
        [$a, $b] = $po->items->all();
        Auth::setUser($buyer);
        $this->refuses(fn () => $receipts->record($po, $buyer, [$a->id => '11']), 'still to come');
        $receipts->record($po, $buyer, [$a->id => '4'], [$a->id => '1']);
        $this->assertSame(4000, $receipts->received($po)[$a->id]);
        $this->assertSame('in_transit', $po->fresh()->status);
        $this->refuses(fn () => $receipts->record($po, $buyer, [$a->id => '7']), 'still to come');
        $receipts->record($po, $buyer, [$a->id => '6', $b->id => '2.5']);
        $this->assertSame(100, $receipts->percent($po));
        $this->assertSame('delivered', $po->fresh()->status);
    }

    public function test_only_the_buyer_of_the_order_records_receipts(): void
    {
        [$po, , $sup] = $this->acknowledgedOrder();
        $other = $this->buyer();
        $receipts = app(ReceiptService::class);
        $this->refuses(fn () => $receipts->record($po, $other, [$po->items[0]->id => '1']), 'Only the buyer');
        $this->refuses(fn () => $receipts->record($po, $sup, [$po->items[0]->id => '1']), 'Only the buyer');
        Livewire::actingAs($sup)->test(OrderShow::class, ['number' => $po->number])->call('receive')->assertDispatched('toast');
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_an_invoice_that_agrees_with_order_and_receipt_is_matched(): void
    {
        [$po, $buyer, $sup] = $this->acknowledgedOrder();
        [$a, $b] = $po->items->all();
        Auth::setUser($buyer);
        app(ReceiptService::class)->record($po, $buyer, [$a->id => '10', $b->id => '2.5']);
        Auth::setUser($sup);
        $inv = app(InvoiceService::class)->submit($po->fresh()->load('items'), $sup, ['supplier_ref' => 'S-1001', 'issued_on' => now()->toDateString()], [$a->id => ['quantity' => '10', 'price' => '12.50'], $b->id => ['quantity' => '2.5', 'price' => '400']]);
        $this->assertSame('matched', $inv->status);
        $this->assertSame(112500, $inv->total_cents);
        $this->assertSame('invoiced', $po->fresh()->status);
        $this->refuses(fn () => app(InvoiceService::class)->submit($po->fresh()->load('items'), $sup, ['supplier_ref' => 'S-1001', 'issued_on' => now()->toDateString()], [$a->id => ['quantity' => '1', 'price' => '12.50']]), 'already sent');
    }

    public function test_the_three_way_match_finds_price_and_quantity_differences(): void
    {
        [$po, $buyer, $sup] = $this->acknowledgedOrder();
        [$a, $b] = $po->items->all();
        Auth::setUser($buyer);
        app(ReceiptService::class)->record($po, $buyer, [$a->id => '4']);
        Auth::setUser($sup);
        $service = app(InvoiceService::class);
        $inv = $service->submit($po->fresh()->load('items'), $sup, ['supplier_ref' => 'S-2', 'issued_on' => now()->toDateString()], [$a->id => ['quantity' => '6', 'price' => '13.00']]);
        $this->assertSame('disputed', $inv->status);
        $this->assertCount(2, $inv->issues);
        $this->assertStringContainsString('the order says', $inv->issues[0]);
        $this->assertStringContainsString('received so far', $inv->issues[1]);

        // The buyer cannot approve a mismatch silently, but can with a reason.
        Auth::setUser($buyer);
        $this->refuses(fn () => $service->approve($inv, $buyer), 'explain why');
        $this->refuses(fn () => $service->markPaid($inv, $buyer), 'approved');
        $this->assertSame('approved', $service->approve($inv, $buyer, 'Price increase agreed by email on Monday')->status);
        $this->assertSame('paid', $service->markPaid($inv->fresh(), $buyer)->status);
    }

    public function test_a_later_receipt_clears_the_quantity_difference_on_rematch(): void
    {
        [$po, $buyer, $sup] = $this->acknowledgedOrder();
        [$a] = $po->items->all();
        Auth::setUser($sup);
        $service = app(InvoiceService::class);
        $inv = $service->submit($po, $sup, ['supplier_ref' => 'S-3', 'issued_on' => now()->toDateString()], [$a->id => ['quantity' => '10', 'price' => '12.50']]);
        $this->assertSame('disputed', $inv->status);
        Auth::setUser($buyer);
        app(ReceiptService::class)->record($po->fresh()->load('items'), $buyer, [$a->id => '10']);
        Livewire::actingAs($buyer)->test(InvoiceShow::class, ['number' => $inv->number])->call('rematch');
        $this->assertSame('matched', $inv->fresh()->status);
        $this->assertNull($inv->fresh()->issues);
    }

    public function test_invoices_are_private_to_the_two_parties(): void
    {
        [$po, $buyer, $sup] = $this->acknowledgedOrder();
        Auth::setUser($sup);
        $inv = app(InvoiceService::class)->submit($po, $sup, ['supplier_ref' => 'S-4', 'issued_on' => now()->toDateString()], [$po->items[0]->id => ['quantity' => '1', 'price' => '12.50']]);
        $this->actingAs($this->supplierUser())->get(route('invoices.show', $inv))->assertNotFound();
        $this->actingAs($this->buyer())->get(route('invoices.show', $inv))->assertNotFound();
        $this->actingAs($buyer)->get(route('invoices.show', $inv))->assertOk()->assertSee('Three-way match');
        Livewire::actingAs($sup)->test(InvoiceShow::class, ['number' => $inv->number])->call('approve')->assertForbidden();
        $this->refuses(fn () => app(InvoiceService::class)->reject($inv, $this->buyer(), 'Not my order at all'), 'Only the buyer');
    }

    public function test_supplier_risk_rises_with_expired_documents_and_falls_when_they_are_in_order(): void
    {
        Storage::fake('local');
        $service = app(SupplierInsightService::class);
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        Auth::setUser($buyer);
        $this->assertSame('low', $service->risk($sup->supplier->load('documents', 'scorecards', 'actions'))['level']);
        $service->addDocument($sup->supplier, $buyer, ['type' => 'insurance', 'title' => 'Liability insurance', 'expires_on' => now()->subMonth()->toDateString()]);
        $service->score($sup->supplier, $buyer, '2026-Q3', ['quality' => 2, 'delivery' => 3, 'price' => 3, 'service' => 2]);
        $risk = $service->risk($sup->supplier->fresh()->load('documents', 'scorecards', 'actions'));
        $this->assertSame('high', $risk['level']);
        $this->assertSame(25, $sup->supplier->fresh()->rating_tenths);

        // A supplier's own upload waits for verification and is stored privately.
        Auth::setUser($sup);
        $doc = $service->addDocument($sup->supplier, $sup, ['type' => 'registration', 'title' => 'Registration'], UploadedFile::fake()->create('reg.pdf', 20, 'application/pdf'));
        $this->assertSame('pending', $doc->status);
        Storage::disk('local')->assertExists($doc->path);
        $this->actingAs($this->supplierUser())->get(route('suppliers.document', [$sup->supplier_id, $doc->id]))->assertForbidden();
        $this->actingAs($sup)->get(route('suppliers.document', [$sup->supplier_id, $doc->id]))->assertOk();
        $this->refuses(fn () => $service->score($sup->supplier, $buyer, '2026', ['quality' => 5, 'delivery' => 5, 'price' => 5, 'service' => 5]), 'period');
    }

    public function test_a_supplier_sees_only_its_own_360_page(): void
    {
        $sup = $this->supplierUser();
        $other = $this->supplierUser();
        $this->actingAs($sup)->get(route('suppliers.show', $sup->supplier_id))->assertOk();
        $this->actingAs($sup)->get(route('suppliers.show', $other->supplier_id))->assertForbidden();
        $this->actingAs($this->buyer())->get(route('suppliers.show', $other->supplier_id))->assertOk()->assertSee('Risk');
    }

    public function test_field_assessment_goes_from_request_to_approval(): void
    {
        $service = app(AssessmentService::class);
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $worker = User::factory()->create(['role' => 'supplier_worker', 'supplier_id' => $sup->supplier_id])->fresh();
        Auth::setUser($buyer);
        $fa = $service->request($buyer, $sup->supplier, 'Site survey', FieldAssessment::DEFAULT_CHECKLIST, null, $worker->id);
        $this->refuses(fn () => $service->request($buyer, $sup->supplier, 'x', ['a'], null, $buyer->id), 'user of that supplier');

        Auth::setUser($worker);
        $this->refuses(fn () => $service->submit($fa, $worker), 'every checklist point');
        $answers = array_fill(0, 5, ['ok' => 'yes', 'note' => '']);
        $answers[2] = ['ok' => false, 'note' => ''];
        $fa = $service->save($fa, $worker, $answers, 'Access road is narrow but usable.');
        $this->refuses(fn () => $service->submit($fa, $worker), 'explain the "no"');
        $answers[2]['note'] = 'Quantity on site is 5% higher';
        $fa = $service->submit($service->save($fa, $worker, $answers, 'Access road is narrow but usable.'), $worker);
        $this->assertSame('submitted', $fa->status);
        $this->refuses(fn () => $service->save($fa, $worker, $answers, 'x'), 'already submitted');

        Auth::setUser($buyer);
        $this->refuses(fn () => $service->decide($fa, $buyer, false, 'no'), '10 characters');
        $this->assertSame('rejected', $service->decide($fa, $buyer, false, 'Please attach the site contact name')->status);
        Auth::setUser($worker);
        $fa = $service->submit($service->save($fa->fresh(), $worker, $answers, 'Contact is Mr Reid. Access road is narrow.'), $worker);
        Auth::setUser($buyer);
        $this->assertSame('approved', $service->decide($fa, $buyer, true)->status);

        $this->actingAs($this->supplierUser())->get(route('assessments.show', $fa))->assertNotFound();
        $this->actingAs($worker)->get(route('assessments.index'))->assertOk()->assertSee('Site survey');
    }

    public function test_information_request_collects_answers_and_seeds_an_rfq(): void
    {
        $service = app(RfiService::class);
        $buyer = $this->buyer();
        $a = $this->supplierUser();
        $b = $this->supplierUser();
        $outsider = $this->supplierUser();
        Auth::setUser($buyer);
        $rfi = $service->save($buyer, ['title' => 'Precast capacity', 'deadline_at' => now()->addDays(5)], ['Lead time?', 'Certificates?'], [$a->supplier_id, $b->supplier_id]);
        $this->actingAs($a)->get(route('rfis.show', $rfi))->assertNotFound();
        $rfi = $service->publish($rfi->load('responses'));
        $this->refuses(fn () => $service->toRfq($rfi, $buyer), 'No supplier has answered');

        Auth::setUser($a);
        $this->refuses(fn () => $service->answer($rfi, $a, [0 => 'Four weeks']), 'Question 2');
        $service->answer($rfi, $a, ['Four weeks', 'ISO 9001']);
        $this->refuses(fn () => $service->answer($rfi, $outsider, ['x', 'y']), 'not asked');
        $this->actingAs($outsider)->get(route('rfis.show', $rfi))->assertNotFound();

        Auth::setUser($buyer);
        $this->actingAs($buyer)->get(route('rfis.show', $rfi))->assertOk()->assertSee('Four weeks');
        $rfq = $service->toRfq($rfi->fresh(), $buyer);
        $this->assertSame([$a->supplier_id], $rfq->invitations()->pluck('supplier_id')->all());
        $this->refuses(fn () => $service->toRfq($rfi->fresh(), $buyer), 'already created');
    }

    public function test_questions_are_answered_for_everyone_without_naming_who_asked(): void
    {
        $qa = app(QaService::class);
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel Ltd']);
        $b = $this->supplierUser(['name' => 'Beta Metals Ltd']);
        $rfq = $this->openRfq($buyer, [$a, $b]);
        Auth::setUser($a);
        $q = $qa->ask($rfq, $a, 'Is delivery to the north gate possible?');
        $this->refuses(fn () => $qa->ask($rfq, $this->supplierUser(), 'Can we join this request too?'), 'not taking part');
        $this->refuses(fn () => $qa->answer($q, $a, 'Yes'), 'Only the buyer');

        // Unanswered: only the asker sees it.
        $this->actingAs($b)->get(route('quotes.form', $rfq))->assertOk()->assertDontSee('north gate');
        Auth::setUser($buyer);
        $qa->answer($q, $buyer, 'Yes, between 07:00 and 15:00.');
        $this->actingAs($b)->get(route('quotes.form', $rfq))->assertSee('north gate')->assertSee('07:00 and 15:00')->assertDontSee('Alpha Steel Ltd');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $b->id]);
    }

    public function test_a_best_and_final_round_lets_quoted_suppliers_revise_and_blocks_awarding(): void
    {
        $bafo = app(BafoService::class);
        $buyer = $this->buyer();
        $a = $this->supplierUser();
        $b = $this->supplierUser();
        $late = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$a, $b, $late]);
        $this->quote($rfq, $a, ['10.00', '100.00']);
        $this->quote($rfq, $b, ['11.00', '100.00']);
        Auth::setUser($buyer);
        $this->refuses(fn () => $bafo->open($rfq, $buyer, now()->addDay()), 'closed request');
        $rfq = $this->closeRfq($rfq);

        // Closed: nobody can change a quotation.
        try {
            $this->quote($rfq, $a, ['9.00', '100.00']);
            $this->fail('A closed request accepted a quotation.');
        } catch (BusinessRule) {
        }
        Auth::setUser($buyer);
        $round = $bafo->open($rfq, $buyer, now()->addDay()->toDateTimeString(), 'Best price please');
        $this->assertContains('A best-and-final round is still open. Wait for its deadline or close it.', app(AwardService::class)->rfqBlockers($rfq));

        $q = $this->quote($rfq, $a, ['9.00', '100.00']);
        $this->assertSame(34000, $q->total_cents);
        $this->assertSame(2, $q->revision);
        // A supplier that never quoted cannot enter through the round.
        try {
            $this->quote($rfq, $late, ['1.00', '1.00']);
            $this->fail('A supplier without a quotation joined the round.');
        } catch (BusinessRule) {
        }
        Auth::setUser($buyer);
        $bafo->close($round);
        $this->assertSame([], app(AwardService::class)->rfqBlockers($rfq->fresh()));
    }

    public function test_reverse_auction_enforces_the_step_hides_prices_and_writes_final_totals(): void
    {
        $service = app(AuctionService::class);
        $buyer = $this->buyer();
        $a = $this->supplierUser();
        $b = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$a, $b]);
        $qa = $this->quote($rfq, $a, ['10.00', '100.00']); // 100 + 250 = 350.00
        $this->quote($rfq, $b, ['12.00', '100.00']);       // 120 + 250 = 370.00
        $rfq = $this->closeRfq($rfq);
        Auth::setUser($buyer);
        $auction = $service->open($rfq, $buyer, 30, 100);
        $this->refuses(fn () => $service->open($rfq, $buyer), 'already had an auction');
        $this->assertSame(36630, $service->ceilingFor($auction, $b->supplier_id));

        Auth::setUser($b);
        $this->refuses(fn () => $service->bid($auction, $b, '369.00'), 'or lower');
        $service->bid($auction, $b, '340.00');
        $this->assertSame($b->supplier_id, array_key_first($service->standing($auction->fresh())));
        $this->refuses(fn () => $service->bid($auction, $this->supplierUser(), '1.00'), 'does not take part');

        // The supplier page shows its own rank, never the competitor's total.
        Livewire::actingAs($a)->test(AuctionPage::class, ['number' => $rfq->number])->assertSee('2 of 2')->assertDontSee('340.00')->set('amount', '333.33')->call('bid');
        $this->assertSame($a->supplier_id, array_key_first($service->standing($auction->fresh())));

        Auth::setUser($buyer);
        $service->end($auction->fresh());
        $qa = $qa->fresh();
        $this->assertSame(33333, $qa->total_cents);
        $this->assertSame(33333, (int) $qa->items()->sum('total_cents'), 'line totals add up to the final total exactly');
        $this->assertSame(2, $qa->revision);
        $this->refuses(fn () => $service->bid($auction->fresh(), $a, '300.00'), 'ended');
    }

    public function test_a_bid_in_the_last_minutes_extends_the_auction(): void
    {
        $service = app(AuctionService::class);
        $buyer = $this->buyer();
        $a = $this->supplierUser();
        $b = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$a, $b]);
        $this->quote($rfq, $a, ['10.00', '100.00']);
        $this->quote($rfq, $b, ['12.00', '100.00']);
        Auth::setUser($buyer);
        $auction = $service->open($this->closeRfq($rfq), $buyer, 30);
        $auction->update(['ends_at' => now()->addSeconds(30)]);
        Auth::setUser($a);
        $service->bid($auction, $a, '300.00');
        $this->assertTrue($auction->fresh()->ends_at->gt(now()->addSeconds(100)));
        $auction->update(['ends_at' => now()->subSecond()]);
        $this->assertSame(1, $service->endExpired());
        $this->assertSame('ended', $auction->fresh()->status);
    }

    public function test_messages_stay_between_the_buyer_side_and_one_supplier(): void
    {
        $service = app(MessageService::class);
        $buyer = $this->buyer();
        $a = $this->supplierUser();
        $b = $this->supplierUser();
        Auth::setUser($buyer);
        $c = $service->start($buyer, $a->supplier, 'Delivery window', 'Can you deliver on Friday?');
        $this->assertSame(1, $service->unread($a));
        $this->assertSame(0, $service->unread($buyer));
        $this->assertSame(0, $service->unread($b));
        $this->refuses(fn () => $service->post($c, $b, 'Hello'), 'not part');
        $this->refuses(fn () => $service->start($b, $a->supplier, 'x', 'y'), 'your own company');

        Livewire::actingAs($a)->test(Messages::class)->assertSee('Delivery window')->call('pick', $c->id)->assertSee('Can you deliver on Friday?')->set('body', 'Yes, Friday morning.')->call('send');
        $this->assertSame(0, $service->unread($a));
        $this->assertSame(1, $service->unread($buyer));
        Livewire::actingAs($b)->test(Messages::class)->assertDontSee('Delivery window')->set('c', $c->id)->assertDontSee('Friday morning');
        $worker = User::factory()->create(['role' => 'supplier_worker', 'supplier_id' => $a->supplier_id])->fresh();
        $this->actingAs($worker)->get(route('messages.index'))->assertForbidden();
    }

    public function test_every_part_two_page_opens_for_the_roles_that_may_see_it(): void
    {
        [$po, $buyer, $sup] = $this->acknowledgedOrder();
        $admin = $this->admin();
        foreach (['requisitions.index', 'requisitions.create', 'contracts.index', 'contracts.create', 'invoices.index', 'assessments.index', 'rfis.index', 'rfis.create', 'messages.index'] as $r) {
            $this->actingAs($buyer)->get(route($r))->assertOk();
            $this->actingAs($admin)->get(route($r))->assertOk();
        }
        foreach (['contracts.index', 'invoices.index', 'assessments.index', 'rfis.index', 'messages.index'] as $r) {
            $this->actingAs($sup)->get(route($r))->assertOk();
        }
        foreach (['requisitions.index', 'contracts.create', 'rfis.create'] as $r) {
            $this->actingAs($sup)->get(route($r))->assertForbidden();
        }
        $this->actingAs($buyer)->get(route('orders.show', $po))->assertOk()->assertSee('Receipts and invoices')->assertSee('Record receipt');
        $this->actingAs($sup)->get(route('orders.show', $po))->assertOk()->assertSee('Send invoice')->assertDontSee('Record receipt');
        $this->actingAs($buyer)->get(route('invoices.index', ['tab' => 'receipts']))->assertOk();
        $this->actingAs($buyer)->get(route('dashboard'))->assertOk();
        $this->actingAs($sup)->get(route('dashboard'))->assertOk();
    }
}
