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

    expect($css)->toContain("48source-prototype-exact.css", "48source-exact-adapters.css");
    expect($css)->not->toContain("48source-exact-scoped.css");
    expect($layout)->not->toContain('role-sidebar', 'role-header', "config('source48.redesign_enabled')");
    expect($layout)->toContain('x-layout.source-shell');
});

it('uses the approved supplier dashboard without a legacy fallback', function () {
    $view = file_get_contents(resource_path('views/livewire/supplier/dashboard.blade.php'));
    expect($view)->toContain('livewire.supplier.approved-dashboard')->not->toContain('source48.redesign_enabled');
});

it('loads the actual approved grid and sidebar stylesheet without CSS scope', function () {
    $appCss = file_get_contents(resource_path('css/app.css'));
    $referenceCss = file_get_contents(resource_path('css/48source-prototype-exact.css'));
    expect($appCss)->toContain("@import './48source-prototype-exact.css';");
    expect($appCss)->not->toContain('48source-exact-scoped.css');
    expect($referenceCss)->toContain('.app{display:grid;grid-template-columns:var(--sbw) minmax(0,1fr)', '.sb{background:var(--sb)', '.topbar{height:var(--tbh)');
});

it('uses the approved interface for the primary role-specific catalog and sourcing screens', function () {
    $paths = [
        'supplier/rfq/index.blade.php',
        'supplier/rfq/show.blade.php',
        'supplier/rfq/quote-form.blade.php',
        'supplier/field/rfq/index.blade.php',
        'supplier/products/index.blade.php',
        'supplier/markets/index.blade.php',
        'seller/products/index.blade.php',
        'seller/markets/index.blade.php',
        'buyer/products/index.blade.php',
        'buyer/markets/index.blade.php',
        'products/index.blade.php',
        'markets/index.blade.php',
    ];

    foreach ($paths as $path) {
        $content = file_get_contents(resource_path('views/livewire/'.$path));
        expect($content)->not->toBeFalse();
        expect(
            str_contains($content, 'class="stack"')
                || str_contains($content, '<x-source.')
        )->toBeTrue();
        expect($content)->not->toContain('bg-gradient-to', 'shadow-2xl');
    }
});

it('does not expose other suppliers quotations from supplier RFQ details', function () {
    $show = file_get_contents(app_path('Livewire/Supplier/Rfq/Show.php'));
    expect($show)->toContain('where(\'supplier_id\', $user->id)')
        ->not->toContain("'quotes.supplier'", "'quotes.items'");
});

it('keeps approved-detail screens free of the deprecated gradient interface', function () {
    $paths = [
        'products/show.blade.php', 'seller/products/show.blade.php', 'supplier/products/show.blade.php',
        'markets/show.blade.php', 'seller/markets/show.blade.php', 'supplier/markets/show.blade.php',
        'orders/show.blade.php', 'seller/orders/show.blade.php', 'supplier/orders/show.blade.php',
        'monitoring/rfq/index.blade.php', 'supplier/field/rfq/show.blade.php',
        'logs/index.blade.php', 'buyer/logs/index.blade.php', 'seller/logs/index.blade.php',
        'supplier/logs/index.blade.php', 'supplier/field/logs/index.blade.php',
    ];
    foreach ($paths as $path) {
        $view = file_get_contents(resource_path('views/livewire/'.$path));
        expect($view)->not->toBeFalse();
        expect($view)->not->toContain('bg-gradient-to-br', 'shadow-2xl');
        expect(
            str_contains($view, 'class="stack"') ||
            str_contains($view, '<x-source.')
        )->toBeTrue();
    }
});
it('guards seller product and market detail ownership', function () {
    $product = file_get_contents(app_path('Livewire/Seller/Products/Show.php'));
    $market = file_get_contents(app_path('Livewire/Seller/Markets/Show.php'));
    expect($product)->toContain("where('user_id', auth()->id())");
    expect($market)->toContain('abort_unless($market->user_id === auth()->id(), 403);');
});
