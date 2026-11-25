<?php

namespace Database\Factories;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Market> */
class MarketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Ticarət Mərkəzi',
            'city' => fake()->randomElement(['Bakı','Gəncə','Sumqayıt','Şəki']),
            'address' => fake()->streetAddress(), 'status' => 'active',
        ];
    }
}
