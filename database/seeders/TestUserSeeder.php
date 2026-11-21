<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Seed test users for development and testing.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo users may only be seeded locally or in tests.');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        // Create admin/buyer user
        User::factory()->create([
            'name' => 'Buyer Admin',
            'email' => 'buyer@48source.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'is_buyer' => true,
            'is_seller' => false,
            'is_supplier' => false,
            'company_name' => '48Source Demo Corp',
            'is_active' => true,
        ]);

        // Create supplier users
        User::factory()->supplier()->create([
            'name' => 'Supplier One',
            'email' => 'supplier1@48source.test',
            'password' => Hash::make('password'),
            'company_name' => 'Supplier Corp',
            'is_active' => true,
        ]);

        User::factory()->supplier()->create([
            'name' => 'Supplier Two',
            'email' => 'supplier2@48source.test',
            'password' => Hash::make('password'),
            'company_name' => 'Supplier Industries',
            'is_active' => true,
        ]);

        // Create seller user
        User::factory()->seller()->create([
            'name' => 'Seller One',
            'email' => 'seller@48source.test',
            'password' => Hash::make('password'),
            'company_name' => 'Seller LLC',
            'is_active' => true,
        ]);

        $this->command->info('Test users created successfully!');
        $this->command->info('---');
        $this->command->info('Buyer: buyer@48source.test / password');
        $this->command->info('Supplier 1: supplier1@48source.test / password');
        $this->command->info('Supplier 2: supplier2@48source.test / password');
        $this->command->info('Seller: seller@48source.test / password');
    }
}
