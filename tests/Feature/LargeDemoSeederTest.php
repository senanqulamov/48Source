<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Database\Seeders\LargeDemoSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class LargeDemoSeederTest extends TestCase
{
    public function test_legacy_seeder_aliases_run_the_same_full_pipeline_idempotently(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(LargeDemoSeeder::class);

        $this->assertSame(240, DB::table('rfqs')->count());
        $this->assertSame(130, DB::table('suppliers')->count());
        $this->assertSame(105, DB::table('users')->where('role','buyer')->count());
        $this->assertSame(105, DB::table('users')->where('role','seller')->count());
    }

    public function test_old_destructive_flag_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(LargeDemoSeeder::class)->run(fresh: true);
    }
}
