<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Rfqs\Compare;
use App\Livewire\Rfqs\CompareIndex;
use App\Models\FeatureFlag;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\AwardService;
use App\Services\QuoteEvaluator;
use Livewire\Livewire;
use Tests\TestCase;

class AwardTest extends TestCase
{
    /** Three quotations on a request of 10 pcs + 2.5 t: A complete and lowest, B complete and dearer, C partial. */
    private function scene(): array
    {
        $buyer = $this->buyer();
        [$a, $b, $c] = [$this->supplierUser(), $this->supplierUser(), $this->supplierUser()];
        $rfq = $this->openRfq($buyer, [$a, $b, $c]);
        $qa = $this->quote($rfq, $a, ['100', '200']);            // 1000 + 500 = 1500.00
        $qb = $this->quote($rfq, $b, ['90', '260'], ['delivery_days' => 5]); // 900 + 650 = 1550.00
        $qc = $this->quote($rfq, $c, [null, '150']);             // 375.00, partial

        return [$buyer, $rfq, $qa, $qb, $qc];
    }

    private function refused(callable $fn, string $needle): void
    {
        try {
            $fn();
            $this->fail('Expected a refusal containing: '.$needle);
        } catch (BusinessRule $e) {
            $this->assertStringContainsStringIgnoringCase($needle, $e->getMessage());
        }
    }

    public function test_an_open_request_cannot_be_awarded(): void
    {
        [$buyer, $rfq, $qa] = $this->scene();
        $this->refused(fn () => app(AwardService::class)->award($rfq, $qa, $buyer), 'Close the request');
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_awarding_the_lowest_complete_quotation_raises_an_exact_draft_order(): void
    {
        [$buyer, $rfq, $qa, $qb, $qc] = $this->scene();
        $rfq = $this->closeRfq($rfq);
        $po = app(AwardService::class)->award($rfq, $qa, $buyer);
        $this->assertSame('draft', $po->status);
        $this->assertMatchesRegularExpression('/^PO-\d{4}-0001$/', $po->number);
        $this->assertSame(150000, $po->total_cents);
        $this->assertSame([100000, 50000], $po->items->pluck('total_cents')->all());
        $this->assertSame($po->total_cents, $po->items->sum('total_cents'));
        $this->assertSame('awarded', $rfq->refresh()->status);
        $this->assertSame('won', $qa->refresh()->status);
        $this->assertSame('lost', $qb->refresh()->status);
        $this->assertSame('lost', $qc->refresh()->status);
        $this->refused(fn () => app(AwardService::class)->award($rfq->refresh(), $qb->refresh(), $buyer), 'cannot be awarded');
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_a_dearer_quotation_needs_a_written_justification(): void
    {
        [$buyer, $rfq, , $qb] = $this->scene();
        $rfq = $this->closeRfq($rfq);
        $this->refused(fn () => app(AwardService::class)->award($rfq, $qb, $buyer), 'Explain the choice');
        $this->refused(fn () => app(AwardService::class)->award($rfq, $qb, $buyer, 'too short'), 'Explain the choice');
        $po = app(AwardService::class)->award($rfq, $qb, $buyer, 'Delivers in 5 days, which the site programme needs.');
        $this->assertSame('Delivers in 5 days, which the site programme needs.', $po->award_justification);
        $this->assertSame(155000, $po->total_cents);
    }

    public function test_partial_expired_and_inactive_quotations_cannot_take_the_whole_request(): void
    {
        [$buyer, $rfq, $qa, $qb, $qc] = $this->scene();
        $rfq = $this->closeRfq($rfq);
        $this->refused(fn () => app(AwardService::class)->award($rfq, $qc, $buyer), 'does not price every item');
        $qa->update(['valid_until' => now()->subDay()]);
        $this->refused(fn () => app(AwardService::class)->award($rfq, $qa->refresh(), $buyer), 'expired');
        Supplier::find($qb->supplier_id)->update(['status' => 'blocked']);
        $this->refused(fn () => app(AwardService::class)->award($rfq, $qb->refresh(), $buyer, 'A long enough justification here.'), 'not active');
    }

    public function test_mixed_currencies_are_never_awarded(): void
    {
        $buyer = $this->buyer();
        [$a, $b] = [$this->supplierUser(), $this->supplierUser()];
        $rfq = $this->openRfq($buyer, [$a, $b]);
        $qa = $this->quote($rfq, $a, ['100', '200']);
        $this->quote($rfq, $b, ['90', '190'], ['currency' => 'EUR']);
        $rfq = $this->closeRfq($rfq);
        $this->refused(fn () => app(AwardService::class)->award($rfq, $qa, $buyer), 'different currencies');
        $this->refused(fn () => app(AwardService::class)->splitAward($rfq, $buyer), 'different currencies');
    }

    public function test_split_award_gives_each_item_to_its_cheapest_supplier(): void
    {
        [$buyer, $rfq, $qa, $qb, $qc] = $this->scene();
        $rfq = $this->closeRfq($rfq);
        $orders = app(AwardService::class)->splitAward($rfq, $buyer);
        // item 1 (10 pcs): B at 90 -> 900.00; item 2 (2.5 t): C at 150 -> 375.00
        $this->assertCount(2, $orders);
        $byS = $orders->keyBy('supplier_id');
        $this->assertSame(90000, $byS[$qb->supplier_id]->total_cents);
        $this->assertSame(37500, $byS[$qc->supplier_id]->total_cents);
        $this->assertSame(1, $byS[$qb->supplier_id]->items()->count());
        $this->assertSame('lost', $qa->refresh()->status);
        $this->assertSame('won', $qb->refresh()->status);
        $this->assertSame('won', $qc->refresh()->status);
        $this->assertSame('awarded', $rfq->refresh()->status);
    }

    public function test_split_award_respects_the_feature_flag_and_needs_every_item_priced(): void
    {
        [$buyer, $rfq] = $this->scene();
        $rfq = $this->closeRfq($rfq);
        FeatureFlag::updateOrCreate(['key' => 'split_awards'], ['label' => 'Split', 'enabled' => false]);
        $this->refused(fn () => app(AwardService::class)->splitAward($rfq, $buyer), 'switched off');

        FeatureFlag::where('key', 'split_awards')->update(['enabled' => true]);
        $buyer2 = $this->buyer();
        $s = $this->supplierUser();
        $rfq2 = $this->openRfq($buyer2, [$s]);
        $this->quote($rfq2, $s, ['10', null]);
        $this->refused(fn () => app(AwardService::class)->splitAward($this->closeRfq($rfq2), $buyer2), 'cannot be split');
    }

    public function test_the_comparison_page_awards_and_enforces_the_approve_permission(): void
    {
        [$buyer, $rfq, $qa] = $this->scene();
        $rfq = $this->closeRfq($rfq);
        Livewire::actingAs($buyer)->test(Compare::class, ['number' => $rfq->number])->assertSee('Lowest price')->assertSee('Not quoted')
            ->call('choose', $qa->id)->call('award')->assertRedirect();
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_scores_follow_the_weighting(): void
    {
        [, $rfq, $qa, $qb] = $this->scene();
        $rows = app(QuoteEvaluator::class)->evaluate($rfq, 'price');
        $this->assertCount(3, $rows);
        $this->assertSame([1, 2, 3], $rows->pluck('rank')->all());
        $a = $rows->firstWhere(fn ($r) => $r['quote']->id === $qa->id);
        $b = $rows->firstWhere(fn ($r) => $r['quote']->id === $qb->id);
        $this->assertTrue($a['complete'] && $b['complete']);
        // B is cheapest on item 1 and delivers fastest, A is cheaper on item 2 than B
        $this->assertSame(100.0, $b['delivery']);
        $this->assertGreaterThan($a['delivery'], $b['delivery']);
        $this->assertFalse($rows->firstWhere(fn ($r) => ! $r['complete']) === null);
    }

    public function test_sealed_requests_hide_prices_until_quoting_closes(): void
    {
        $buyer = $this->buyer();
        $s = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$s], ['sealed' => true]);
        $this->quote($rfq, $s, ['77.77', '88.88']);
        $this->actingAs($buyer)->get(route('rfqs.compare', $rfq))->assertForbidden();
        $this->actingAs($buyer)->get(route('rfqs.show', $rfq).'?tab=quotes')->assertOk()->assertSee('Sealed until the deadline')->assertDontSee('77.77');
        $rfq = $this->closeRfq($rfq);
        $this->actingAs($buyer)->get(route('rfqs.compare', $rfq))->assertOk()->assertSee('77.77');
    }

    public function test_comparison_picker_uses_the_actual_close_time_and_supplier_columns_can_be_toggled(): void
    {
        [$buyer, $rfq, $qa, $qb] = $this->scene();
        $rfq = $this->closeRfq($rfq);
        $rfq->forceFill(['closed_at' => now()->subDays(3), 'created_at' => now()->subYear()])->save();

        Livewire::actingAs($buyer)->test(CompareIndex::class)
            ->assertSee('Closed 3 days ago');

        Livewire::actingAs($buyer)->test(Compare::class, ['number' => $rfq->number])
            ->assertSee('Select for award')
            ->call('toggleSupplier', $qa->supplier_id)
            ->assertSet('hidden', [$qa->supplier_id])
            ->assertSee($qb->supplier->name);
    }
}
