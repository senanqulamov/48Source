<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // The afterCreating UserFactory callback expects roles to exist.
        // Provision permissions before any feature file's beforeEach factory runs.
        if (str_contains(static::class, 'TestCase') || app()->environment('testing')) {
            Artisan::call('db:seed', [
                '--class' => \Database\Seeders\RolesAndPermissionsSeeder::class,
                '--force' => true,
            ]);
        }
    }
}
