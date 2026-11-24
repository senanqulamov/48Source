<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Support\Nav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The menus and page structure of the accepted prototype. */
class PrototypeParityTest extends TestCase
{
    use RefreshDatabase;

    private function menu($user): array
    {
        return collect(Nav::for($user))->mapWithKeys(fn ($g) => [$g[0] => collect($g[1])->pluck('label')->all()])->all();
    }

    public function test_the_administrator_menu_follows_the_prototype(): void
    {
        $this->assertSame([
            'Overview' => ['Dashboard'],
            'Sourcing' => ['RFQs', 'Compare quotations', 'Purchase orders', 'Requisitions', 'Information requests', 'Contracts'],
            'Finance' => ['Invoices & receipts', 'Spend & budgets', 'Savings'],
            'Insight' => ['Supplier 360', 'Assistant'],
            'Marketplace' => ['Categories', 'Markets', 'Marketplace orders'],
            'People' => ['Suppliers', 'Users & roles', 'Messages'],
            'Monitoring' => ['RFQ monitoring', 'SLA tracker', 'KPI reports'],
            'System' => ['Notifications', 'Logs', 'System health', 'Feature flags', 'Import / export', 'Email outbox', 'Accounting export'],
        ], $this->menu($this->admin()));
    }

    public function test_the_buyer_and_supplier_menus_follow_the_prototype(): void
    {
        $buyer = $this->menu($this->buyer());
        $this->assertSame(['Workspace', 'Sourcing', 'Finance', 'Insight', 'Network', 'Marketplace', 'Records'], array_keys($buyer));
        $this->assertSame(['Invoices & receipts', 'Spend & budgets', 'Savings'], $buyer['Finance']);
        $this->assertSame(['Marketplace', 'Basket', 'My marketplace orders'], $buyer['Marketplace']);
        $this->assertSame(['Activity logs'], $buyer['Records']);

        $supplier = $this->menu($this->supplierUser());
        $this->assertSame(['Workspace', 'Sourcing', 'Marketplace', 'Team', 'Records', 'Company'], array_keys($supplier));
        $this->assertSame(['Company profile', 'Contracts', 'Invoices', 'Information requests'], $supplier['Company']);
        $this->assertSame(['Invitations & quotes', 'Purchase orders'], $supplier['Sourcing']);
    }

    public function test_each_split_page_is_its_own_page_and_old_addresses_redirect(): void
    {
        $admin = $this->admin();
        foreach (['spend.index' => 'Spend & budgets', 'savings.index' => 'Savings', 'kpi.index' => 'KPI reports', 'monitoring.index' => 'RFQ monitoring', 'sla.index' => 'SLA tracker',
            'health.index' => 'System health', 'flags.index' => 'Feature flags', 'impex.index' => 'Import / export', 'sup360.index' => 'Supplier 360'] as $route => $title) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee('<h1 class="ph-t">'.e($title).'</h1>', false);
        }
        $this->actingAs($admin)->get('/reports?tab=savings')->assertRedirect(route('savings.index'));
        $this->actingAs($admin)->get('/reports')->assertRedirect(route('spend.index'));
        $this->actingAs($admin)->get('/system?tab=flags')->assertRedirect(route('flags.index'));

        // The same people as before are kept out.
        $supplier = $this->supplierUser();
        foreach (['spend.index', 'savings.index', 'kpi.index', 'monitoring.index', 'sla.index', 'health.index', 'flags.index', 'impex.index', 'sup360.index', 'reports.index', 'system.index'] as $route) {
            $this->actingAs($supplier)->get(route($route))->assertForbidden();
        }
        foreach (['health.index', 'flags.index', 'impex.index'] as $route) {
            $this->actingAs($this->buyer())->get(route($route))->assertForbidden();
        }
    }

    public function test_everyone_reads_their_own_activity_and_only_administrators_read_all(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $supplier = $this->supplierUser();
        ActivityLog::create(['user_id' => $buyer->id, 'actor_name' => $buyer->name, 'area' => 'RFQ', 'action' => 'Buyer did the first thing', 'subject_label' => 'A-1']);
        ActivityLog::create(['user_id' => $supplier->id, 'actor_name' => $supplier->name, 'area' => 'Quote', 'action' => 'Supplier did the second thing', 'subject_label' => 'B-2']);

        $this->actingAs($buyer)->get(route('logs.index'))->assertOk()->assertSee('My activity')->assertSee('Buyer did the first thing')->assertDontSee('Supplier did the second thing');
        $this->actingAs($supplier)->get(route('logs.index'))->assertOk()->assertSee('Supplier did the second thing')->assertDontSee('Buyer did the first thing');
        $this->actingAs($admin)->get(route('logs.index'))->assertOk()->assertSee('Activity logs')->assertSee('Buyer did the first thing')->assertSee('Supplier did the second thing');
    }

    public function test_quick_search_only_returns_what_the_role_may_open(): void
    {
        $buyer = $this->buyer();
        $mine = $this->supplierUser(['name' => 'Alpha Steelworks']);
        $other = $this->supplierUser(['name' => 'Beta Steelworks']);
        $rfq = $this->openRfq($buyer, [$mine]);
        $hidden = $this->openRfq($buyer, [$other]);

        $this->actingAs($buyer)->getJson(route('search.quick', ['q' => 'a']))->assertOk()->assertExactJson([]);
        $found = collect($this->actingAs($buyer)->getJson(route('search.quick', ['q' => 'Steelworks']))->assertOk()->json())->pluck('title');
        $this->assertTrue($found->contains('Alpha Steelworks') && $found->contains('Beta Steelworks'));

        $titles = collect($this->actingAs($mine)->getJson(route('search.quick', ['q' => 'RFQ-']))->assertOk()->json())->pluck('title');
        $this->assertTrue($titles->contains($rfq->number));
        $this->assertFalse($titles->contains($hidden->number), 'A supplier found a request it was not invited to.');
        $this->assertSame([], $this->actingAs($mine)->getJson(route('search.quick', ['q' => 'Steelworks']))->json(), 'A supplier must not search the supplier directory.');
        $this->getJson(route('search.quick', ['q' => 'steel']));
    }

    public function test_list_pages_carry_the_summary_band(): void
    {
        $buyer = $this->buyer();
        Requisition::create(['number' => 'PR-T-1', 'requester_id' => $buyer->id, 'title' => 'Site signage', 'status' => 'pending', 'total_cents' => 240000, 'submitted_at' => now()]);
        $this->actingAs($buyer)->get(route('requisitions.index'))->assertOk()->assertSee('class="strip"', false)->assertSee('Waiting for my approval')->assertSee('In approval')->assertSee('Purchase requisitions');
        $this->actingAs($buyer)->get(route('contracts.index'))->assertOk()->assertSee('class="strip"', false)->assertSee('Active contracts');
        $this->actingAs($buyer)->get(route('invoices.index'))->assertOk()->assertSee('class="strip"', false)->assertSee('Deliveries awaited');
        $this->actingAs($this->admin())->get(route('monitoring.index'))->assertOk()->assertSee('class="strip"', false)->assertSee('Active RFQs');
        Supplier::create(['name' => 'Pending Co', 'status' => 'pending']);
        $this->actingAs($buyer)->get(route('sup360.index'))->assertOk()->assertSee('High-risk suppliers')->assertSee('Pending Co');
    }
}
