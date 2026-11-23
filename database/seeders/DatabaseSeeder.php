<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference data only. It creates no user accounts and no passwords.
 * Create the first administrator with:  php artisan app:create-admin you@company.com "Your Name"
 * Load invented demo data (never on production) with:  php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public const CATEGORIES = [
        'Materials & equipment' => ['Construction materials', 'Industrial equipment', 'Steel & metals'],
        'Technology & office' => ['IT hardware', 'Office equipment'],
        'Services' => ['Facility maintenance', 'Logistics services', 'Professional services'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $group => $children) {
            $g = Category::firstOrCreate(['slug' => Str::slug($group)], ['name' => $group]);
            foreach ($children as $name) {
                Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'parent_id' => $g->id]);
            }
        }
        foreach (FeatureFlag::DEFAULTS as $key => [$label, $on]) {
            FeatureFlag::firstOrCreate(['key' => $key], ['label' => $label, 'enabled' => $on]);
        }
    }
}
