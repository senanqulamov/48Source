<?php

use App\Models\User;

it('renders the approved 48Source login screen', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in to your workspace')
        ->assertSee('Every request, quotation and award');
});

it('renders the redesigned admin dashboard with real data', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Procurement overview')
        ->assertSee('source-ui app', false);
});

it('renders the approved buyer RFQ listing', function () {
    $buyer = User::factory()->buyer()->create();
    $this->actingAs($buyer)
        ->get(route('buyer.rfq.index'))
        ->assertOk()
        ->assertSee('Manage requests')
        ->assertSee('dt-top', false);
});

it('renders the approved supplier dashboard and invitation table', function () {
    $supplier = User::factory()->supplier()->create();
    $this->actingAs($supplier)
        ->get(route('supplier.dashboard'))
        ->assertOk()
        ->assertSee('Supplier workspace')
        ->assertSee('Invitations requiring response');

    $this->actingAs($supplier)
        ->get(route('supplier.invitations.index'))
        ->assertOk()
        ->assertSee('Invitations & RFQs')
        ->assertSee('dt-top', false);

    $this->actingAs($supplier)
        ->get(route('supplier.quotes.index'))
        ->assertOk()
        ->assertSee('My quotations');
});

it('renders the approved seller and field worker dashboards', function () {
    $seller = User::factory()->seller()->create();
    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertSee('Seller workspace');

    $worker = User::factory()->create([
        'role' => 'supplier_worker',
        'supplier_id' => $seller->id,
        'is_seller' => false,
        'is_supplier' => false,
        'is_buyer' => false,
    ]);

    $this->actingAs($worker)
        ->get(route('supplier.field.dashboard'))
        ->assertOk()
        ->assertSee('Field worker workspace');
});

it('renders the unified approved layout with a defined role', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    expect($layout)->toContain('@endphp', 'x-layout.source-shell')
        ->not->toContain('@endph', 'x-layout.role-sidebar', 'x-layout.role-header');
});

it('uses the single approved design shell with role-specific navigation', function () {
    $shell = file_get_contents(resource_path('views/components/layout/source-shell.blade.php'));
    foreach (['buyer', 'seller', 'supplier'] as $role) {
        expect($shell)->toContain("'".$role."' =>");
    }
    expect($shell)->toContain('default =>', 'view_rfqs', 'view_orders');
});
