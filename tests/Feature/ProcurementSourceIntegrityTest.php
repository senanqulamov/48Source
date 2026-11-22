<?php

it('registers procurement purchase orders separately from marketplace orders', function () {
    $routes = file_get_contents(base_path('routes/web.php'));
    $migration = file_get_contents(database_path('migrations/2026_11_22_000001_create_procurement_purchase_orders.php'));
    expect($routes)->toContain('procurement.orders.index', 'procurement.orders.show');
    expect($migration)->toContain("Schema::create('procurement_purchase_orders'");
    expect($migration)->not->toContain("Schema::table('orders'");
});

it('does not permit awarding by directly setting the RFQ status', function () {
    foreach (['Livewire/Buyer/Rfq/Show.php', 'Livewire/Monitoring/Rfq/Show.php'] as $path) {
        $source = file_get_contents(app_path($path));
        expect($source)->toContain('if ($value === \'awarded\')');
        expect($source)->toContain('AwardAndPurchaseOrderService');
    }
});

it('requires auditable justification for non-cheapest awards', function () {
    $service = file_get_contents(app_path('Services/Procurement/AwardAndPurchaseOrderService.php'));
    expect($service)->toContain('mb_strlen($reason) < 15');
    expect($service)->toContain("'award_justification'");
    expect($service)->toContain('lockForUpdate()', 'DB::transaction');
});
