<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
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

        // Keep these credentials stable and make this seeder safe to run again.
        $this->seedUser([
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
        $this->seedUser([
            'name' => 'Supplier One',
            'email' => 'supplier1@48source.test',
            'password' => Hash::make('password'),
            'role' => 'supplier',
            'is_buyer' => false,
            'is_seller' => false,
            'is_supplier' => true,
            'company_name' => 'Supplier Corp',
            'supplier_status' => 'active',
            'supplier_approved_at' => now(),
            'is_active' => true,
        ]);

        $this->seedUser([
            'name' => 'Supplier Two',
            'email' => 'supplier2@48source.test',
            'password' => Hash::make('password'),
            'role' => 'supplier',
            'is_buyer' => false,
            'is_seller' => false,
            'is_supplier' => true,
            'company_name' => 'Supplier Industries',
            'supplier_status' => 'active',
            'supplier_approved_at' => now(),
            'is_active' => true,
        ]);

        // Create seller user
        $this->seedUser([
            'name' => 'Seller One',
            'email' => 'seller@48source.test',
            'password' => Hash::make('password'),
            'role' => 'seller',
            'is_buyer' => false,
            'is_seller' => true,
            'is_supplier' => false,
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

    /**
     * Create or refresh a predictable local account and its role assignment.
     *
     * @param array<string, mixed> $attributes
     */
    private function seedUser(array $attributes): void
    {
        $user = User::updateOrCreate(
            ['email' => $attributes['email']],
            $attributes,
        );

        $role = Role::where('name', $attributes['role'])->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
