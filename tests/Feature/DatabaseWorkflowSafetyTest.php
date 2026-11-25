<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DatabaseWorkflowSafetyTest extends TestCase
{
    public function test_reference_catalog_can_be_seeded_twice_without_creating_business_accounts(): void
    {
        $this->seed(ReferenceCatalogSeeder::class);
        $count = DB::table('categories')->count();
        $this->seed(ReferenceCatalogSeeder::class);

        $this->assertSame($count, DB::table('categories')->count());
        $this->assertSame(0, DB::table('users')->count());
        $this->assertGreaterThanOrEqual(280, DB::table('products')->count());
        $this->assertTrue(Schema::hasTable('workflow_fixture_records'));
    }

    public function test_default_seeder_rejects_preexisting_business_data_without_deleting_it(): void
    {
        $supplier = Supplier::factory()->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to mix fixtures');

        try {
            $this->seed(DatabaseSeeder::class);
        } finally {
            $this->assertDatabaseHas('suppliers', ['id'=>$supplier->id]);
            $this->assertSame(1, DB::table('suppliers')->count());
            $this->assertSame(0, DB::table('rfqs')->count());
        }
    }
}
