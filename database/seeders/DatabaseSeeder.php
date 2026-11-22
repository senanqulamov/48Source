<?php

namespace Database\Seeders;

use App\Models\Log;
use App\Models\Market;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('48Source demo seeding is restricted to local and testing environments.');
        }

        $this->command->info('🌱 Starting comprehensive database seeding...');

        // STEP 1: Ensure core roles and permissions exist before any users are created
        $this->command->info('📋 Step 1: Creating roles and permissions...');
        $this->call(RolesAndPermissionsSeeder::class);

        // Always include the documented local test accounts in the default demo data.
        // This keeps `php artisan db:seed` and TestUserSeeder credentials consistent.
        $this->call(TestUserSeeder::class);

        // STEP 2: Create users with proper distribution
        // 50 suppliers, 40 buyers, 10 sellers, 2 workers, and 1 admin.
        $this->command->info('👥 Step 2: Creating users...');

        // Create a predictable demo administrator only in local/testing.
        $admin = User::updateOrCreate(
            ['email' => 'admin@48source.test'],
            [
                'name' => '48Source Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'is_buyer' => true,
                'is_seller' => true,
                'is_supplier' => true,
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $adminRole = \App\Models\Role::where('name', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // Create exactly 10 sellers
        $this->command->info('  → Creating 10 sellers...');
        $sellers = collect();
        for ($i = 1; $i <= 10; $i++) {
            $seller = User::factory()->seller()->create([
//                'name' => "Seller User {$i}",
                'email' => "seller_{$i}@example.com",
            ]);
            $sellers->push($seller);
        }
        $this->command->info("  ✓ Created {$sellers->count()} sellers");

        // Create exactly 50 suppliers
        $this->command->info('  → Creating 50 suppliers...');
        $suppliers = collect();
        for ($i = 1; $i <= 50; $i++) {
            $supplier = User::factory()->supplier()->create([
//                'name' => "Supplier User {$i}",
                'email' => "supplier_{$i}@example.com",
            ]);
            $suppliers->push($supplier);
        }
        $this->command->info("  ✓ Created {$suppliers->count()} suppliers");

        // Create exactly 40 buyers
        $this->command->info('  → Creating 40 buyers...');
        $buyers = collect();
        for ($i = 1; $i <= 40; $i++) {
            $buyer = User::factory()->buyer()->create([
//                'name' => "Buyer User {$i}",
                'email' => "buyer_{$i}@example.com",
            ]);
            $buyers->push($buyer);
        }
        $this->command->info("  ✓ Created {$buyers->count()} buyers");

        // Create one login for each worker role, linked to its parent account.
        $marketWorker = User::factory()->marketWorker()->create([
            'name' => 'Demo Market Worker',
            'email' => 'market_worker@48source.test',
            'password' => Hash::make('password'),
            'seller_id' => $sellers->first()->id,
        ]);
        $supplierWorker = User::factory()->create([
            'name' => 'Demo Supplier Worker',
            'email' => 'supplier_worker@48source.test',
            'password' => Hash::make('password'),
            'role' => 'supplier_worker',
            'supplier_id' => $suppliers->first()->id,
            'is_buyer' => false,
            'is_seller' => false,
            'is_supplier' => false,
            'is_active' => true,
        ]);

        // STEP 3: Create markets - each seller gets exactly 10 markets
        $this->command->info('🏪 Step 3: Creating markets (10 per seller = 100 total)...');
        $markets = collect();
        foreach ($sellers as $index => $seller) {
            $sellerNumber = $index + 1;
            for ($m = 1; $m <= 10; $m++) {
                $market = Market::factory()->create([
                    'user_id' => $seller->id,
                ]);
                $markets->push($market);
            }
        }
        $this->command->info("  ✓ Created {$markets->count()} markets");

        // STEP 4: Create products - each market gets 5-30 products
        $this->command->info('📦 Step 4: Creating products (5-30 per market)...');
        $products = collect();
        $totalProducts = 0;
        foreach ($markets as $market) {
            $productCount = rand(5, 30);
            for ($p = 0; $p < $productCount; $p++) {
                $product = Product::factory()->create([
                    'market_id' => $market->id,
                    'supplier_id' => $suppliers->random()->id,
                ]);
                $products->push($product);
                $totalProducts++;
            }
        }
        $this->command->info("  ✓ Created {$totalProducts} products across {$markets->count()} markets");

        // STEP 5: Create orders for suppliers
        $this->command->info('🛒 Step 5: Creating orders for suppliers...');
        $orderCount = 0;
        foreach ($suppliers as $supplier) {
            // Each supplier gets 2-5 orders
            $numOrders = rand(2, 5);
            for ($o = 0; $o < $numOrders; $o++) {
                // Pick a random market (and thus a seller)
                $market = $markets->random();
                $seller = $market->seller;

                /** @var \App\Models\Order $order */
                $order = Order::create([
                    'user_id' => $supplier->id,
                    'seller_id' => $seller->id,
                    'total' => 0,
                    'status' => fake()->randomElement(['pending', 'accepted', 'processing', 'completed', 'cancelled']),
                ]);

                // Attach 1-4 products from this market
                $marketProducts = $products->where('market_id', $market->id);
                if ($marketProducts->isEmpty()) {
                    continue;
                }

                $lineProducts = $marketProducts->random(min(rand(1, 4), $marketProducts->count()));
                $total = 0;

                foreach ($lineProducts as $product) {
                    $qty = rand(1, 5);
                    $unit = (float) $product->price;
                    $subtotal = round($qty * $unit, 2);

                    $order->items()->create([
                        'product_id' => $product->id,
                        'market_id' => $product->market_id,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'subtotal' => $subtotal,
                    ]);

                    $total += $subtotal;
                }

                $order->forceFill(['total' => $total])->saveQuietly();
                $orderCount++;
            }
        }
        $this->command->info("  ✓ Created {$orderCount} orders for buyers");

        // STEP 6: Create logs with proper user associations
        $this->command->info('📝 Step 6: Creating activity logs...');
        $allUsers = collect([$admin, $marketWorker, $supplierWorker])->merge($sellers)->merge($suppliers)->merge($buyers);
        for ($i = 0; $i < 300; $i++) {
            Log::factory()->create([
                'user_id' => $allUsers->random()->id,
            ]);
        }
        $this->command->info('  ✓ Created 300 activity logs');

        // STEP 7: Create RFQs, Quotes, and supplier invitations
        $this->command->info('📋 Step 7: Creating RFQs, quotes, and supplier interactions...');
        $this->call(RfqSeeder::class);
        $this->command->info('  ✓ RFQ data seeded successfully!');

        // STEP 8: Validation and summary
        $this->command->info('✅ Step 8: Validating seeded data...');

        $nonAdminUsers = User::where('is_admin', false)->count();
        $marketCount = Market::count();
        $productCount = Product::count();
        $orderCountFinal = Order::count();
        $logCount = Log::count();

        $this->command->info("\n📊 Seeding Summary:");
        $this->command->info("  • Total Users: " . ($nonAdminUsers + 1) . " (1 admin + {$nonAdminUsers} regular users)");
        $this->command->info("    - Sellers: {$sellers->count()}");
        $this->command->info("    - Suppliers: {$suppliers->count()}");
        $this->command->info("    - Buyers: {$buyers->count()}");
        $this->command->info("    - Workers: 2 (market and supplier)");
        $this->command->info("  • Markets: {$marketCount}");
        $this->command->info("  • Products: {$productCount}");
        $this->command->info("  • Orders: {$orderCountFinal}");
        $this->command->info("  • Logs: {$logCount}");

        // Verify role assignments
        $usersWithoutRoles = User::doesntHave('roles')->count();
        if ($usersWithoutRoles > 0) {
            $this->command->error("⚠️  Warning: {$usersWithoutRoles} users without roles attached!");
        } else {
            $this->command->info("  • All users have proper role assignments ✓");
        }

        if ($nonAdminUsers !== 106) {
            $this->command->warn("⚠️  Expected 102 non-admin users, got {$nonAdminUsers}");
        }

        // Fail CI/fresh installs if a seeded account cannot use the documented password.
        $demoUsers = User::whereIn('role', ['admin', 'buyer', 'seller', 'supplier', 'market_worker', 'supplier_worker'])->get();
        foreach ($demoUsers as $demoUser) {
            if (! Hash::check('password', $demoUser->password)) {
                throw new \RuntimeException("Demo user {$demoUser->email} does not have the expected local password.");
            }
        }
        if ($usersWithoutRoles > 0 || $nonAdminUsers !== 106) {
            throw new \RuntimeException('Demo user seeding did not create the expected accounts and role assignments.');
        }

        $this->command->info("\n🎉 Database seeding completed successfully!");
    }
}
