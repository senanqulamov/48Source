<?php

it('uses a real four-step administrator RFQ wizard, not the old TallStack modal', function () {
    $view = file_get_contents(resource_path('views/livewire/rfq/create.blade.php'));
    expect($view)->toContain('data-wizard-step="1"', 'data-wizard-step="2"',
        'data-wizard-step="3"', 'data-wizard-step="4"',
        'admin-rfq-wizard-form', 'source-rfq-wizard');
    expect($view)->not->toContain('<x-modal', '<x-select.styled', '<x-number', '$refs.title?.focus(), 250)');
});

it('stores product names and valid supplier invitations atomically', function () {
    $component = file_get_contents(app_path('Livewire/Rfq/Create.php'));
    expect($component)->toContain("DB::transaction(", "product_name' => \$product->name",
        "selectedSuppliers", "SupplierInvited", "status = 'draft'");
    expect($component)->not->toContain("'product_id' => \$product->id,");
    expect($component)->toContain("where('name', 'buyer')", "where('name', 'supplier')");
});
