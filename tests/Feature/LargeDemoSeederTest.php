<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Database\Seeders\LargeDemoSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class LargeDemoSeederTest extends TestCase
{
    public function test_legacy_seeding_entries_delegate_to_the_single_workflow_fixture(): void
    {
        app(DemoSeeder::class)->run();
        app(LargeDemoSeeder::class)->run(seed: 48);

        $this->assertSame(72, DB::table('rfqs')->count());
        $this->assertSame(8, DB::table('suppliers')->count());
    }

    public function test_legacy_fresh_mode_cannot_erase_existing_data(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(LargeDemoSeeder::class)->run(fresh: true);
    }
}
