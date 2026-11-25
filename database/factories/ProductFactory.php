<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Copper cable NYY 3×2.5 mm²', 'IP65 floodlight 100 W',
            'Portland cement CEM I 42.5R', 'Industrial pressure gauge 0–16 bar',
            'Business laptop 14 inch 16 GB', 'Managed PoE switch 24-port',
        ]);

        return [
            'sku' => 'TEST-'.strtoupper(Str::random(10)),
            'name' => $name, 'category_id' => Category::factory(),
            'unit' => 'pcs', 'description' => 'Specification-based catalog item.',
            'status' => 'active',
        ];
    }

    public function inCategory(Category $category): static
    {
        return $this->state(['category_id' => $category->id]);
    }

    public function retired(): static
    {
        return $this->state(['status' => 'retired']);
    }
}
