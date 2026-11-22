<?php

use App\Models\User;

it('renders the approved 48Source login screen', function () {
    config()->set('source48.redesign_enabled', true);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in to your workspace')
        ->assertSee('Every request, quotation and award');
});

it('renders the redesigned admin dashboard with real data', function () {
    config()->set('source48.redesign_enabled', true);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Procurement overview')
        ->assertSee('source-ui app', false);
});

it('renders the approved buyer RFQ listing', function () {
    config()->set('source48.redesign_enabled', true);

    $buyer = User::factory()->buyer()->create();
    $this->actingAs($buyer)
        ->get(route('buyer.rfq.index'))
        ->assertOk()
        ->assertSee('Manage requests')
        ->assertSee('dt-top', false);
});

it('renders the approved supplier dashboard and invitation table', function () {
    config()->set('source48.redesign_enabled', true);

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
    config()->set('source48.redesign_enabled', true);

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
