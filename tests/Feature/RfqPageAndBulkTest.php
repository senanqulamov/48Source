<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Rfqs\Index as RfqIndex;
use App\Livewire\Rfqs\Show;
use App\Livewire\Suppliers\Index as SupplierIndex;
use App\Livewire\Users\Index as UserIndex;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RfqToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The request page as in the accepted design, and bulk actions on the lists. */
class RfqPageAndBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_supplier_is_reminded_at_most_once_in_twelve_hours(): void
    {
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $b = $this->supplierUser(['name' => 'Beta Metals']);
        $rfq = $this->openRfq($buyer, [$a, $b]);
        $tools = app(RfqToolsService::class);

        $tools->remindOne($rfq, $buyer, $a->supplier_id);
        $this->assertSame(1, $a->notifications()->count());
        $this->assertSame(0, $b->notifications()->count());
        try {
            $tools->remindOne($rfq, $buyer, $a->supplier_id);
            $this->fail('The same supplier was reminded twice.');
        } catch (BusinessRule $e) {
            $this->assertStringContainsString('12 hours', $e->getMessage());
        }
        $this->quote($rfq, $b, ['10.00', '100.00']);
        $this->expectException(BusinessRule::class);
        $tools->remindOne($rfq, $buyer, $b->supplier_id);
    }

    public function test_only_the_buyer_invites_and_only_active_suppliers_once(): void
    {
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $rfq = $this->openRfq($buyer, [$a]);
        $new = Supplier::create(['name' => 'Gamma Works', 'status' => 'active']);
        $pending = Supplier::create(['name' => 'Pending Co', 'status' => 'pending']);
        $tools = app(RfqToolsService::class);

        $tools->invite($rfq, $buyer, $new->id);
        $this->assertTrue($rfq->invitations()->where('supplier_id', $new->id)->exists());
        foreach ([[$buyer, $new->id], [$buyer, $pending->id], [$this->buyer(), $pending->id]] as [$who, $id]) {
            try {
                $tools->invite($rfq, $who, $id);
                $this->fail('An invitation that must be refused went through.');
            } catch (BusinessRule) {
                $this->assertTrue(true);
            }
        }
        $closed = $this->closeRfq($rfq);
        $this->expectException(BusinessRule::class);
        $tools->invite($closed, $buyer, Supplier::create(['name' => 'Late Ltd', 'status' => 'active'])->id);
    }

    public function test_admin_controls_are_for_administrators_and_use_the_normal_rules(): void
    {
        $buyer = $this->buyer();
        $admin = $this->admin();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $rfq = $this->openRfq($buyer, [$a]);

        Livewire::actingAs($buyer)->test(Show::class, ['number' => $rfq->number])->assertDontSee('Admin controls')->call('adminType', 'public')->assertForbidden();
        $this->assertSame('invited', $rfq->fresh()->type);

        $page = Livewire::actingAs($admin)->test(Show::class, ['number' => $rfq->number])->assertSee('Admin controls')->assertSee('Important dates')->assertSee('Sourcing tools')->assertSee('Latest activity');
        $page->call('adminType', 'public');
        $this->assertSame('public', $rfq->fresh()->type);
        $page->call('adminAssessment', true);
        $this->assertTrue($rfq->fresh()->requires_assessment);
        // Changing the status only opens the usual dialog; nothing changes until it is confirmed there.
        $page->call('adminStatus', 'closed')->assertSet('modal', 'close');
        $this->assertSame('open', $rfq->fresh()->status);
        $page->call('adminStatus', 'cancelled')->assertSet('modal', 'cancel');
        $this->assertSame('open', $rfq->fresh()->status);
        $page->call('adminStatus', 'awarded');
        $this->assertSame('open', $rfq->fresh()->status);
    }

    public function test_the_request_tabs_show_what_the_design_shows(): void
    {
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $b = $this->supplierUser(['name' => 'Beta Metals']);
        $rfq = $this->openRfq($buyer, [$a, $b]);
        $this->quote($rfq, $a, ['10.00', '100.00']);

        Livewire::actingAs($buyer)->test(Show::class, ['number' => $rfq->number])
            ->set('tab', 'items')->assertSee('Requested items')->assertSee('Estimated value')
            ->set('tab', 'suppliers')->assertSee('Supplier invitations')->assertSee('1 of 2 have quoted')->assertSee('Invite supplier')->assertSee('Remind')
            ->set('tab', 'files')->assertSee('Related files')->assertSee('Upload')
            ->call('exportItems')->assertFileDownloaded();

        $closed = $this->closeRfq($rfq);
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $closed->number])->set('tab', 'quotes')->assertSee('Accept')->assertDontSee('Price adjustment tool');
        $page = Livewire::actingAs($this->admin())->test(Show::class, ['number' => $closed->number])->set('tab', 'quotes')->assertSee('Price adjustment tool');
        $quote = $closed->quotes()->firstOrFail();
        $before = $quote->total_cents;
        $page->set("adj.{$quote->id}.target", (string) (($before - 1000) / 100))->set("adj.{$quote->id}.reason", 'Discount confirmed by email on 20 Nov')->call('adjustQuote', $quote->id);
        $this->assertSame($before - 1000, $quote->fresh()->total_cents);
        $this->assertSame($before, $quote->fresh()->original_total_cents);
        // A buyer cannot use the tool even by calling it directly.
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $closed->number])->set("adj.{$quote->id}.target", '1.00')->set("adj.{$quote->id}.reason", 'Trying it as a buyer anyway')->call('adjustQuote', $quote->id);
        $this->assertSame($before - 1000, $quote->fresh()->total_cents);
    }

    public function test_bulk_actions_work_on_the_selected_rows_only(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $b = $this->supplierUser(['name' => 'Beta Metals']);
        $one = $this->openRfq($buyer, [$a]);
        $two = $this->openRfq($buyer, [$b]);

        // Requests: export and remind only what is ticked.
        Livewire::actingAs($buyer)->test(RfqIndex::class)->set('selected', [$one->number])->call('exportSelected')->assertFileDownloaded();
        Livewire::actingAs($buyer)->test(RfqIndex::class)->set('selected', [$one->number])->call('remindSelected')->assertSet('selected', []);
        $this->assertSame(1, $a->notifications()->count());
        $this->assertSame(0, $b->notifications()->count());
        Livewire::actingAs($buyer)->test(RfqIndex::class)->call('exportSelected')->assertNoFileDownloaded();

        // Suppliers: invite the selected ones to a request.
        $new = Supplier::create(['name' => 'Gamma Works', 'status' => 'active']);
        $blocked = Supplier::create(['name' => 'Blocked Co', 'status' => 'blocked']);
        Livewire::actingAs($buyer)->test(SupplierIndex::class)->set('selected', [(string) $new->id, (string) $blocked->id, (string) $a->supplier_id])->set('bulkRfq', $one->number)->call('inviteSelected')->assertSet('selected', []);
        $this->assertTrue($one->invitations()->where('supplier_id', $new->id)->exists());
        $this->assertFalse($one->invitations()->where('supplier_id', $blocked->id)->exists());
        $this->assertSame(1, $one->invitations()->where('supplier_id', $a->supplier_id)->count());

        // Users: the administrator's own account and the last administrator stay active.
        $invited = User::create(['name' => 'New Person', 'email' => 'new.person@example.test', 'password' => 'secret-Pass-123', 'role' => 'buyer', 'status' => 'invited'])->fresh();
        Livewire::actingAs($admin)->test(UserIndex::class)->set('selected', [(string) $admin->id, (string) $buyer->id])->call('disableSelected');
        $this->assertSame('active', $admin->fresh()->status);
        $this->assertSame('disabled', $buyer->fresh()->status);
        Livewire::actingAs($admin)->test(UserIndex::class)->set('selected', [(string) $invited->id])->call('resendSelected')->assertSet('selected', []);
        Livewire::actingAs($a)->test(OrderIndex::class)->call('exportSelected')->assertNoFileDownloaded();
        $this->actingAs($a)->get(route('users.index'))->assertForbidden();
    }
}
