<?php

use App\Livewire\Dashboard\Index;
use Livewire\Livewire;

it('keeps the approved identity as the single visible role shell', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $shell = file_get_contents(resource_path('views/components/layout/source-shell.blade.php'));
    $css = file_get_contents(resource_path('css/48source-authoritative-fixes.css'));

    expect($layout)->toContain('data-theme="light"', "48source-theme");
    expect($shell)->toContain('wire:navigate', 'setAppTheme');
    expect($css)->toContain('var(--brand)', 'var(--text)', 'var(--ink)');
    expect($css)->not->toContain('background:var(--primary);border:1px solid var(--primary)');
});

it('uses real server-side data for the dashboard and does not hydrate unused charts', function () {
    $component = file_get_contents(app_path('Livewire/Dashboard/Index.php'));
    $view = file_get_contents(resource_path('views/livewire/dashboard/approved-index.blade.php'));

    expect($component)->toContain('loadApprovedDashboard()', 'ProcurementPurchaseOrder::query()', 'withCount');
    expect($component)->not->toContain("        \$this->loadChartData();");
    expect($view)->toContain('source-dashboard-actions', 'source-dashboard-chart', "approved['attention']");
    expect($view)->not->toContain('RFQ-2026-0418', 'Aldermoor Holdings');
});

it('keeps the global search inside the approved design and avoids navigation listener leaks', function () {
    $search = file_get_contents(resource_path('views/livewire/search/global-search.blade.php'));
    $shell = file_get_contents(resource_path('views/components/layout/source-shell.blade.php'));
    expect($search)->toContain('source-search-dialog', 'x-on:keydown.window', 'wire:navigate');
    expect($search)->not->toContain('window.addEventListener', 'document.addEventListener');
    expect($shell)->toContain('open-search', '48source-sidebar-collapsed');
});

it('keeps admin and buyer RFQ lists on one shared server-driven table', function () {
    $table = file_get_contents(resource_path('views/components/source/rfq-table.blade.php'));
    $admin = file_get_contents(app_path('Livewire/Rfq/Index.php'));
    $buyer = file_get_contents(app_path('Livewire/Buyer/Rfq/Index.php'));

    expect($table)->toContain('wire:click="changeSort', 'wire:model.live="typeFilter"',
        'clearTableFilters', 'source-column-chooser', 'supplier_invitations_count');
    expect($admin)->toContain("changeSort(string \$column)", "updatedBuyerFilter(): void",
        "'buyer_id'", "whereIn('id', Request::query()->select('buyer_id'))");
    expect($buyer)->toContain("where('buyer_id', auth()->id())",
        "changeSort(string \$column)", "updatedTypeFilter(): void");
});
