<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RealisticWorkflowSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DatabaseWorkflowSafetyTest extends TestCase
{
    public function test_reference_seed_is_idempotent_and_creates_no_business_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = DB::table('categories')->count();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, DB::table('categories')->count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('rfqs')->count());
        $this->assertTrue(Schema::hasTable('workflow_fixture_records'));
    }

    public function test_fixture_refuses_populated_database_without_modifying_it(): void
    {
        $supplier = Supplier::factory()->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already contains business records');

        try {
            app(RealisticWorkflowSeeder::class)->run();
        } finally {
            $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
            $this->assertSame(1, DB::table('suppliers')->count());
            $this->assertSame(0, DB::table('workflow_fixture_records')->count());
        }
    }
}
