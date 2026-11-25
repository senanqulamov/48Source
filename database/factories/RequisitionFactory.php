<?php

namespace Database\Factories;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Requisition> */
class RequisitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'REQ-T'.fake()->unique()->numerify('######'),
            'requester_id' => User::factory()->buyer(),
            'title' => fake()->randomElement([
                'Cement and aggregate replenishment','Electrical panel upgrade',
                'IT equipment for new office','HVAC replacement parts',
            ]),
            'justification' => 'Operational procurement requirement.',
            'currency' => 'AZN', 'total_cents' => 0, 'status' => 'draft',
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => 'pending', 'submitted_at' => now()]);
    }
}
