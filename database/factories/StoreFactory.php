<?php

namespace Database\Factories;

use App\Models\Market;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'market_id' => Market::factory(),
            'name' => fake()->unique()->company().' Təchizat',
            'stall' => fake()->bothify('B-###'),
            'status' => 'active',
        ];
    }

    public function atMarket(Market $market): static
    {
        return $this->state(['market_id' => $market->id]);
    }
}
