<?php

use Illuminate\Support\Facades\Route;

it('registers distinct permission-protected quotation comparison routes', function () {
    $list = Route::getRoutes()->getByName('procurement.compare.index');
    $detail = Route::getRoutes()->getByName('procurement.compare.show');

    expect($list)->not->toBeNull();
    expect($detail)->not->toBeNull();
    expect($list->gatherMiddleware())->toContain('can:view_rfqs');
    expect($detail->gatherMiddleware())->toContain('can:view_rfqs');
});

it('keeps comparison data isolated to owned requests and actual submitted quotes', function () {
    $list = file_get_contents(app_path('Livewire/Procurement/Comparison/Index.php'));
    $show = file_get_contents(app_path('Livewire/Procurement/Comparison/Show.php'));

    expect($list)->toContain("where('buyer_id', \$actor->id)", "whereIn('status', ['submitted', 'accepted'])");
    expect($show)->toContain('buyer_id === $actor->id', 'Quote::query()', 'request_item_id');
    expect($show)->toContain('AwardAndPurchaseOrderService', '$service->award(');
    expect($show)->toContain('Normalize currencies before selecting a quotation', 'Only a quotation covering all requested items');
    expect($show)->not->toContain('Order::create(', 'rand(', 'fake(');
});

it('uses the shared approved matrix instead of invented ranking inputs', function () {
    $view = file_get_contents(resource_path('views/livewire/procurement/comparison/show.blade.php'));
    expect($view)->toContain('source-comparison-matrix', 'Not quoted',
        'different currencies', 'selectForAward(', 'Confirm award');
    expect($view)->not->toContain('Helena Voss', 'Aldermoor Holdings');
});


it('keeps approved shared control geometry and brand action color', function () {
    $css = file_get_contents(resource_path('css/48source-control-system.css'));
    expect($css)->toContain('width:min(520px,calc(100vw - 16px))', 'border-radius:20px', 'background:var(--brand)');
    expect($css)->not->toContain('background:var(--primary);\n  border-color:var(--primary-h);');
});
