<?php

namespace App\Console\Commands;

use Database\Seeders\LargeDemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class DemoData extends Command
{
    protected $signature = 'app:demo-data {--scale=1 : Multiplier for the generated volumes} {--seed=48 : Reproducible random seed} {--fresh : Delete prior generated demo records first}';

    protected $description = 'Create a large, invented, reproducible 48Source demo dataset (never production).';

    public function handle(LargeDemoSeeder $seeder): int
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be generated in production.');
        }
        $scale = max(1, (int) $this->option('scale'));
        $seed = (int) $this->option('seed');
        if ($this->option('fresh') && ! $this->confirm('This removes records created by app:demo-data. Continue?')) {
            $this->warn('No data was changed.');
            return self::SUCCESS;
        }

        $seeder->setCommand($this);
        $seeder->run($scale, $seed, (bool) $this->option('fresh'));

        return self::SUCCESS;
    }
}
