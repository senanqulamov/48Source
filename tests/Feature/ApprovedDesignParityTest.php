<?php

it('keeps the approved HTML and exact design stylesheet together', function () {
    $reference = file_get_contents(base_path('design/reference/48source-redesign.html'));
    $stylesheet = file_get_contents(resource_path('css/48source-prototype-exact.css'));

    expect($reference)->not->toBeFalse();
    expect($stylesheet)->not->toBeFalse();

    preg_match('/<style>(.*?)<\/style>/s', $reference, $matches);
    expect(trim($matches[1] ?? ''))->toBe(trim($stylesheet));
    expect($reference)->toContain('Bricolage Grotesque', 'Onest', 'Quotation comparison');
});

it('registers approved redesign assets and uses the new layout exclusively', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($css)->toContain("48source-exact-scoped.css", "48source-exact-adapters.css");
    expect($layout)->not->toContain('role-sidebar', 'role-header', "config('source48.redesign_enabled')");
    expect($layout)->toContain('x-layout.source-shell');
});

it('uses the approved supplier dashboard without a legacy fallback', function () {
    $view = file_get_contents(resource_path('views/livewire/supplier/dashboard.blade.php'));
    expect($view)->toContain('livewire.supplier.approved-dashboard')->not->toContain('source48.redesign_enabled');
});
