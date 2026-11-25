<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Compatibility alias. The former random demo generator is intentionally retired.
 * All local procurement scenarios now use RealisticWorkflowSeeder.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RealisticWorkflowSeeder::class);
    }
}
