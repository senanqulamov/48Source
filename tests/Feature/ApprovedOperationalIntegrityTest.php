<?php

it('requires the complete supplier quote component to receive its items', function () {
    $view = file_get_contents(resource_path('views/components/source/quotation-form.blade.php'));
    expect($view)->toContain("'items',", "lines: \$wire.entangle('items')");
});

it('uses bounded server-side aggregation for dashboard charts', function () {
    $source = file_get_contents(app_path('Livewire/Dashboard/Index.php'));
    expect($source)->toContain('COUNT(*) AS activity_count', "groupByRaw('DATE(created_at)')");
    expect($source)->not->toContain("->pluck('created_at')->map(fn (\$date)");
});

it('writes quotes and line records atomically and prevents repeated submits', function () {
    foreach ([
        'Livewire/Supplier/Quotes/Create.php',
        'Livewire/Supplier/Quotes/Edit.php',
        'Livewire/Rfq/QuoteForm.php',
    ] as $path) {
        $source = file_get_contents(app_path($path));
        expect($source)->toContain('DB::transaction(', 'lockForUpdate()', 'RFQ quantities cannot be changed');
    }

    $generic = file_get_contents(app_path('Livewire/Rfq/QuoteForm.php'));
    expect($generic)->toContain('use ($requested, $user, $invitation, $totalAmount)');
});

it('registers market worker routes with role-restricted Livewire components', function () {
    $routes = file_get_contents(base_path('routes/web.php'));
    $seller = file_get_contents(app_path('Livewire/Seller/Tasks/Index.php'));
    $worker = file_get_contents(app_path('Livewire/MarketWork/Today.php'));
    expect($routes)->toContain("name('seller.tasks.index')", "name('market-work.today')");
    expect($seller)->toContain("whereKey(\$data['workerId'])", "'market_worker'");
    expect($worker)->toContain("hasRole('market_worker')", "where('worker_id', auth()->id())");
});

it('requires all field checks before assessment submission and private photo access', function () {
    $source = file_get_contents(app_path('Livewire/Supplier/Field/Rfq/Show.php'));
    expect($source)->toContain('Answer every checklist item before submitting',
        "Storage::disk('local')->download", "status' => 'submitted'");
});
