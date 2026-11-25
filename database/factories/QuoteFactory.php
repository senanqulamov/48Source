<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quote> */
class QuoteFactory extends Factory
{
    public function definition(): array
    {
        // Only a draft shell: tests must explicitly add quote_items and calculate
        // totals before submission. Do not fabricate an awarded order from this.
        return [
            'rfq_id' => Rfq::factory()->withItems(),
            'supplier_id' => Supplier::factory(),
            'status' => 'draft', 'currency' => 'AZN',
            'total_cents' => 0, 'revision' => 0,
        ];
    }

    public function forRequest(Rfq $rfq): static
    {
        return $this->state(['rfq_id' => $rfq->id, 'currency' => $rfq->currency]);
    }

    public function fromSupplier(Supplier $supplier): static
    {
        return $this->state(['supplier_id' => $supplier->id]);
    }
}
