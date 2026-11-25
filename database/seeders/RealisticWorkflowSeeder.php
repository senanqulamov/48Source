<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backwards-compatible entry point for older tooling and tests.
 * The default DatabaseSeeder is now the single source of truth.
 */
class RealisticWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
    }
}
