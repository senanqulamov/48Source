<?php

it('replaces legacy modal and slide controls across procurement and management modules', function () {
    $paths = ["resources/views/livewire/markets/create.blade.php","resources/views/livewire/categories/create.blade.php","resources/views/livewire/privacy/roles/create.blade.php","resources/views/livewire/seller/workers/create.blade.php","resources/views/livewire/seller/workers/update.blade.php","resources/views/livewire/seller/markets/create.blade.php","resources/views/livewire/supplier/workers/create.blade.php","resources/views/livewire/products/create.blade.php","resources/views/livewire/rfq/create.blade.php","resources/views/livewire/orders/create.blade.php","resources/views/livewire/buyer/rfq/update.blade.php","resources/views/livewire/monitoring/rfq/create.blade.php","resources/views/livewire/seller/orders/view.blade.php","resources/views/livewire/monitoring/rfq/workflow-events.blade.php","resources/views/livewire/markets/update.blade.php","resources/views/livewire/categories/update.blade.php","resources/views/livewire/privacy/roles/update.blade.php","resources/views/livewire/seller/markets/update.blade.php","resources/views/livewire/supplier/workers/update.blade.php","resources/views/livewire/privacy/user-roles/update.blade.php","resources/views/livewire/privacy/role-permissions/update.blade.php","resources/views/livewire/rfq/update.blade.php","resources/views/livewire/products/update.blade.php","resources/views/livewire/monitoring/rfq/items.blade.php","resources/views/livewire/orders/update.blade.php","resources/views/livewire/monitoring/rfq/update.blade.php"];
    $paths = array_merge($paths, [
        'resources/views/livewire/users/supplier-actions.blade.php',
        'resources/views/livewire/buyer/rfq/approved-invite-modal.blade.php',
        'resources/views/livewire/logs/log-view.blade.php',
        'resources/views/livewire/orders/view.blade.php',
        'resources/views/livewire/buyer/rfq/create.blade.php',
    ]);
    foreach ($paths as $path) {
        $view = file_get_contents(resource_path(str_replace('resources/', '', $path)));
        expect($view)->toContain('source-dialog', 'wire:keydown.escape');
        expect($view)->not->toContain('<x-modal', '<x-slide');
    }
});

it('uses one approved shared modal/drawer style layer', function () {
    $css = file_get_contents(resource_path('css/48source-control-system.css'));
    expect($css)->toContain('.source-dialog .source-modal-body', '.source-dialog .source-drawer-body');
    expect($css)->toContain('.source-dialog button[type=submit]:not(.btn)', '.source-ui .dt-tools');
});

it('renders approved operational pages instead of TallStack cards and tables', function () {
    foreach ([
        'resources/views/livewire/health/index.blade.php',
        'resources/views/livewire/sla/index.blade.php',
        'resources/views/livewire/notifications/index.blade.php',
        'resources/views/livewire/orders/index.blade.php',
        'resources/views/livewire/privacy/roles/show.blade.php',
    ] as $path) {
        $view = file_get_contents(resource_path(str_replace('resources/', '', $path)));
        expect($view)->toContain('class="stack"');
        expect($view)->not->toContain('<x-card', '<x-table');
    }
});

it('does not nest Blade translation expressions when converting modal titles', function () {
    foreach ([
        'resources/views/livewire/monitoring/rfq/workflow-events.blade.php',
        'resources/views/livewire/buyer/rfq/create.blade.php',
        'resources/views/livewire/seller/orders/view.blade.php',
    ] as $path) {
        $view = file_get_contents(resource_path(str_replace('resources/', '', $path)));
        expect($view)->not->toContain("__('{{");
    }
});

it('replaces legacy cards on account and supplier management details', function () {
    foreach ([
        'resources/views/livewire/users/show.blade.php',
        'resources/views/livewire/supplier/workers/show.blade.php',
        'resources/views/livewire/supplier/field-assessment/create.blade.php',
        'resources/views/livewire/supplier/field-assessment/show.blade.php',
        'resources/views/livewire/supplier/quotes/edit.blade.php',
        'resources/views/livewire/privacy/users/show.blade.php',
        'resources/views/livewire/settings/feature-flags.blade.php',
        'resources/views/livewire/seller/workers/edit.blade.php',
        'resources/views/livewire/rfq/quote-form.blade.php',
    ] as $path) {
        $source = file_get_contents(resource_path(str_replace('resources/', '', $path)));
        expect($source)->not->toContain('<x-card');
        expect($source)->toMatch('/class="card"|<x-source\\.quotation-form/');
    }
});

it('uses the approved error design for supported HTTP error statuses', function () {
    foreach ([401, 403, 404, 419, 429, 500, 503] as $status) {
        $view = file_get_contents(resource_path('views/errors/'.$status.'.blade.php'));
        expect($view)->toContain('errors.approved');
    }
    $shared = file_get_contents(resource_path('views/errors/approved.blade.php'));
    expect($shared)->toContain('class="source-ui"');
    expect($shared)->not->toContain('bg-gradient-to');
});

it('removes the obsolete admin dashboard backup view', function () {
    expect(file_exists(resource_path('views/livewire/dashboard/index-old-backup.blade.php')))->toBeFalse();
});
