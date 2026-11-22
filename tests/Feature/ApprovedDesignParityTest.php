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

it('registers approved redesign assets and keeps a legacy layout fallback', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($css)->toContain("48source-exact-scoped.css", "48source-exact-adapters.css");
    expect($layout)->toContain("config('source48.redesign_enabled')");
    expect($layout)->toContain('x-layout.source-shell');
});

it('keeps existing production UI off by default when no opt-in is configured', function () {
    expect(config('source48.redesign_enabled'))->toBeBool();
});
