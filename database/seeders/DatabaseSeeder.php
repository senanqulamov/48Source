<?php

namespace Database\Seeders;

use Database\Seeders\Support\Fixture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * The default entry point for "php artisan migrate:fresh --seed".
     * NEVER populate synthetic users/transactions in production or staging.
     */
    public function run(): void
    {
        $this->call(ReferenceCatalogSeeder::class);
        if (app()->environment(['production', 'staging'])) {
            Fixture::note('Safety', 'Reference catalog only; synthetic business records disabled in this environment.');

            return;
        }

        if (! Schema::hasTable('workflow_fixture_records')) {
            throw new RuntimeException('Fixture tracking migration is missing. Run migrations before seeding.');
        }

        if (DB::table('workflow_fixture_records')->where('batch', Fixture::BATCH)->exists()) {
            Fixture::note('Safety', 'Fixture batch already exists. No business records changed.');

            return;
        }

        foreach (['suppliers', 'rfqs', 'quotes', 'purchase_orders', 'stores', 'market_orders', 'invoices', 'requisitions', 'contracts'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("Refusing to mix fixtures with existing {$table}. Use a NEW disposable local database; no business records were changed.");
            }
        }

        Fixture::note('Start', 'Generating a connected business dataset (local/testing only).');
        DB::transaction(function (): void {
            $this->call(BusinessIdentitySeeder::class);
            $this->call(MarketplaceInventorySeeder::class);
            $this->call(ProcurementTransactionSeeder::class);
            $this->call(OperationalHistorySeeder::class);
            Fixture::insert('workflow_fixture_records', [
                'table_name' => 'seed_batch', 'record_id' => 1,
                'batch' => Fixture::BATCH, 'created_at' => now(),
            ]);
        }, attempts: 1);

        Fixture::note('Complete', 'All seed phases committed successfully.');
        Fixture::note('Accounts', 'Example admin: admin@workflow.48source.test');
        Fixture::note('Accounts', 'Buyer: workflow.buyer.0@example.test / Supplier: workflow.supplier.0@example.test');
        Fixture::note('Accounts', 'Local-only password: '.Fixture::password());
        Fixture::note('Warning', 'Fictional people and companies; valid business workflows, NOT real customer transactions.');
    }
}
