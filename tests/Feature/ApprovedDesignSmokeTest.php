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
