<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Compatibility alias for historical commands.
 * The 2,000-RFQ random generator is retired; no destructive --fresh cleanup.
 */
class LargeDemoSeeder extends Seeder
{
    public function run(int $scale = 1, int $seed = 48, bool $fresh = false): void
    {
        if ($scale !== 1 || $fresh) {
            throw new InvalidArgumentException(
                'Legacy scale/fresh options are unsupported. Use RealisticWorkflowSeeder on a fresh disposable database.'
            );
        }

        $this->call(RealisticWorkflowSeeder::class);
    }
}
