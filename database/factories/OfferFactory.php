<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Offer> */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'price_cents' => fake()->numberBetween(1500, 250000),
            'currency' => 'AZN', 'moq_milli' => 1000,
            'stock_milli' => fake()->numberBetween(20, 200) * 1000,
            'lead_days' => fake()->numberBetween(1, 14),
            'status' => 'active',
        ];
    }

    public function forStore(Store $store): static
    {
        return $this->state(['store_id' => $store->id]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(['product_id' => $product->id]);
    }
}
