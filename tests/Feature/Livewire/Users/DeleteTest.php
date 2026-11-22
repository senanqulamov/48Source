<?php

use App\Livewire\Users\Delete;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    $this->user = User::factory()->create();
});

it('renders the approved confirmation control', function () {
    Livewire::test(Delete::class, ['user' => $this->user])
        ->assertOk()
        ->assertSeeHtml('wire:click="confirm"');
});

it('opens the custom confirmation without deleting a user', function () {
    Livewire::test(Delete::class, ['user' => $this->user])
        ->call('confirm')
        ->assertSet('confirming', true)
        ->assertSee('Delete user?');

    expect(User::find($this->user->id))->not->toBeNull();
});

it('requires explicit confirmation before executing a deletion', function () {
    Livewire::test(Delete::class, ['user' => $this->user])
        ->call('delete')->assertForbidden();

    expect(User::find($this->user->id))->not->toBeNull();
});

it('deletes an explicitly confirmed user and emits a refresh event', function () {
    Livewire::test(Delete::class, ['user' => $this->user])
        ->call('confirm')
        ->call('delete')
        ->assertDispatched('deleted')
        ->assertSet('confirming', false);

    expect(User::find($this->user->id))->toBeNull();
});

it('rejects unauthenticated deletion attempts', function () {
    auth()->logout();

    Livewire::test(Delete::class, ['user' => $this->user])
        ->call('confirm')->assertSet('confirming', false);

    expect(User::find($this->user->id))->not->toBeNull();
});
