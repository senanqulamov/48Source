<?php

use App\Models\User;

it('attaches worker and additional roles without persisting temporary factory attributes', function () {
    $worker = User::factory()->marketWorker()->create([
        'email' => 'factory-market-worker@48source.test',
    ]);

    expect($worker->hasRole('market_worker'))->toBeTrue();
    expect($worker->roles()->where('name', 'market_worker')->exists())->toBeTrue();

    $multiRole = User::factory()->buyer()->roles(['buyer', 'supplier'])->create([
        'email' => 'factory-multirole@48source.test',
    ]);

    expect($multiRole->roles()->whereIn('name', ['buyer', 'supplier'])->count())->toBe(2);
    expect($multiRole->getAttributes())->not->toHaveKey('roles_to_attach');
});
