<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('seeds a login for all six local user types', function () {
    $this->artisan('db:seed', ['--class' => \Database\Seeders\DatabaseSeeder::class, '--force' => true])
        ->assertExitCode(0);

    $accounts = [
        'admin' => 'admin@48source.test',
        'buyer' => 'buyer_1@example.com',
        'seller' => 'seller_1@example.com',
        'supplier' => 'supplier_1@example.com',
        'market_worker' => 'market_worker@48source.test',
        'supplier_worker' => 'supplier_worker@48source.test',
    ];

    foreach ($accounts as $role => $email) {
        $user = User::where('email', $email)->firstOrFail();
        expect($user->role)->toBe($role);
        expect($user->roles()->where('name', $role)->exists())->toBeTrue();
        expect(Hash::check('password', $user->password))->toBeTrue();
        expect($user->is_active)->toBeTrue();
    }

    expect(User::count())->toBe(107);
});

it('creates repeatable local test accounts with the documented password', function () {
    $this->artisan('db:seed', ['--class' => \Database\Seeders\TestUserSeeder::class, '--force' => true])
        ->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\TestUserSeeder::class, '--force' => true])
        ->assertExitCode(0);

    foreach ([
        'buyer@48source.test' => 'buyer',
        'supplier1@48source.test' => 'supplier',
        'supplier2@48source.test' => 'supplier',
        'seller@48source.test' => 'seller',
    ] as $email => $role) {
        $user = User::where('email', $email)->firstOrFail();

        expect($user->role)->toBe($role);
        expect($user->roles()->where('name', $role)->exists())->toBeTrue();
        expect(Hash::check('password', $user->password))->toBeTrue();
        expect($user->is_active)->toBeTrue();
    }
});
