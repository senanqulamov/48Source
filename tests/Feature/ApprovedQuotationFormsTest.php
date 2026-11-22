<?php

it('uses one approved two-step quotation form for every existing supplier entry path', function () {
    $shared = file_get_contents(resource_path('views/components/source/quotation-form.blade.php'));
    expect($shared)->toContain("step: 1", "step===2", "wire:submit=\"save\"", "lines: \$wire.entangle('items')", 'grandTotal()', 'Review before submission');
    foreach ([
        'views/livewire/supplier/quotes/approved-create.blade.php',
        'views/livewire/supplier/quotes/edit.blade.php',
        'views/livewire/rfq/quote-form.blade.php',
    ] as $path) {
        $view = file_get_contents(resource_path($path));
        expect($view)->toContain('<x-source.quotation-form', ':items="$items"');
        expect($view)->not->toContain('<x-number', '<x-select.styled');
    }
});

it('enforces RFQ line identity and immutable requested quantities on quotation writes', function () {
    foreach ([
        'Livewire/Supplier/Quotes/Create.php',
        'Livewire/Supplier/Quotes/Edit.php',
        'Livewire/Rfq/QuoteForm.php',
    ] as $path) {
        $php = file_get_contents(app_path($path));
        expect($php)->toContain('Every requested RFQ line must be quoted exactly once', 'RFQ quantities cannot be changed');
        expect($php)->toContain("->items()->get()->keyBy('id')");
    }

    $edit = file_get_contents(app_path('Livewire/Supplier/Quotes/Edit.php'));
    expect($edit)->toContain("where('supplier_id', Auth::id())", "This quotation is no longer editable");
});
