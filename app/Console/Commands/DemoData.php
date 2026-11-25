<?php

namespace App\Console\Commands;

use Database\Seeders\RealisticWorkflowSeeder;
use Illuminate\Console\Command;

/**
 * Backwards-compatible command name; no random bulk modes or destructive flags.
 */
class DemoData extends Command
{
    protected $signature = 'app:demo-data';

    protected $description = 'Seed coherent synthetic procurement workflows into an EMPTY local database (not production).';

    public function handle(RealisticWorkflowSeeder $seeder): int
    {
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
