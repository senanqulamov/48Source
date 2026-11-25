<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'city' => fake()->city(), 'contact_name' => fake()->name(), 'email' => fake()->unique()->companyEmail(), 'status' => 'active', 'rating_tenths' => 40, 'approved_at' => now()];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive', 'approved_at' => now()]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => 'blocked', 'status_reason' => 'Compliance review', 'approved_at' => null]);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'approved_at' => null]);
    }
}
