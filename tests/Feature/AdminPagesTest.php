<?php

namespace Tests\Feature;

use App\Livewire\Accounting;
use App\Livewire\Monitoring;
use App\Livewire\Reports;
use App\Livewire\System;
use App\Models\ActivityLog;
use App\Services\ReminderService;
use App\Services\ReportService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Spend & budgets, KPI reports, SLA tracker, System health and Accounting export as in the accepted design. */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_spend_and_kpi_pages_show_the_design_blocks(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('spend.index'))->assertOk()
            ->assertSee('Total spend')->assertSee('Under contract')->assertSee('Budgets exceeded')
            ->assertSee('Spend by month')->assertSee('Spend by category')->assertSee('Top suppliers')->assertSee('Spend by department')->assertSee('Spend records');
        $this->actingAs($admin)->get(route('kpi.index'))->assertOk()
            ->assertSee('RFQs published')->assertSee('Quotation participation')->assertSee('Request-to-award time')
            ->assertSee('RFQ trends')->assertSee('Purchasing activity')->assertSee('Supplier comparison')->assertSee('12 months');

        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $b = $this->supplierUser(['name' => 'Beta Metals']);
        $rfq = $this->openRfq($buyer, [$a, $b]);
        $this->quote($rfq, $a, ['10.00', '100.00']);
        $report = app(ReportService::class)->kpiReport($buyer, 3);
        $this->assertCount(3, $report['labels']);
        $this->assertSame(1, array_sum($report['published']));
        $this->assertSame([2, 1], [$report['invited'], $report['quoted']]);
        $this->assertSame(50, end($report['participation']));
        $row = $report['suppliers']->firstWhere('name', 'Alpha Steel');
        $this->assertSame([1, 1], [$row['invited'], $row['quoted']]);
        // Another buyer's figures stay empty.
        $this->assertSame(0, array_sum(app(ReportService::class)->kpiReport($this->buyer(), 3)['published']));
        Livewire::actingAs($buyer)->test(Reports::class, ['page' => 'kpis'])->set('period', 6)->assertSee('6 months')->assertSee('Alpha Steel');
    }

    public function test_sla_rules_are_saved_by_administrators_and_used_by_the_check(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $rfq = $this->openRfq($buyer, [$a]);
        $rfq->update(['deadline_at' => now()->addDays(3)]);

        $this->assertSame(1, Setting::int('sla.quote_reminder_days'));
        app(ReminderService::class)->run();
        $this->assertSame(0, $a->notifications()->count(), 'Reminded three days ahead although the rule says one day.');

        Livewire::actingAs($buyer)->test(Monitoring::class, ['page' => 'sla'])->assertSee('SLA rules')->assertSee('Reminders and deadlines')->set('rules.sla_award_days', 9)->call('saveRules')->assertForbidden();
        $this->assertSame(5, Setting::int('sla.award_days'));

        Livewire::actingAs($admin)->test(Monitoring::class, ['page' => 'sla'])->set('rules.sla_quote_reminder_days', 99)->call('saveRules')->assertHasErrors('rules.sla_quote_reminder_days');
        Livewire::actingAs($admin)->test(Monitoring::class, ['page' => 'sla'])->set('rules.sla_quote_reminder_days', 4)->set('rules.sla_award_days', 9)->call('saveRules')->assertHasNoErrors();
        Setting::forget();
        $this->assertSame([4, 9], [Setting::int('sla.quote_reminder_days'), Setting::int('sla.award_days')]);
        app(ReminderService::class)->run();
        $this->assertSame(1, $a->notifications()->count());
    }

    public function test_health_page_shows_services_flags_and_figures(): void
    {
        Livewire::actingAs($this->admin())->test(System::class, ['page' => 'health'])
            ->assertSee('Overall')->assertSee('Events today')->assertSee('Mail delivery')->assertSee('Services')->assertSee('Feature flags')->assertSee('Import & export');
    }

    public function test_accounting_data_sets_download_and_are_logged(): void
    {
        $buyer = $this->buyer();
        $page = Livewire::actingAs($this->admin())->test(Accounting::class)->assertSee('Supplier master data')->assertSee('Export history')->assertSee('Download JSON');
        foreach (array_keys(Accounting::SETS) as $set) {
            $page->call('exportSet', $set, 'csv')->assertFileDownloaded();
        }
        $page->call('exportSet', 'suppliers', 'json')->assertFileDownloaded();
        $this->assertSame(6, ActivityLog::where('area', 'Export')->count());
        $this->actingAs($this->supplierUser())->get(route('accounting.index'))->assertForbidden();
    }
}
