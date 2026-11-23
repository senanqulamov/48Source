<?php

namespace Database\Factories;

use App\Models\Rfq;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rfq> */
class RfqFactory extends Factory
{
    public function definition(): array
    {
        return ['number' => fn () => Sequence::next('RFQ'), 'title' => ucfirst(fake()->words(4, true)), 'type' => 'invited', 'buyer_id' => User::factory(), 'status' => 'draft', 'currency' => 'USD', 'deadline_at' => now()->addDays(7), 'scoring_preset' => 'balanced'];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open', 'published_at' => now()]);
    }

    /** Add items as [name, quantity in thousandths, unit]. */
    public function withItems(array $items = [['Item A', 10000, 'pcs'], ['Item B', 2500, 't']]): static
    {
        return $this->afterCreating(function (Rfq $rfq) use ($items) {
            foreach ($items as $i => [$name, $qty, $unit]) {
                $rfq->items()->create(['position' => $i + 1, 'name' => $name, 'quantity_milli' => $qty, 'unit' => $unit]);
            }
        });
    }
}
