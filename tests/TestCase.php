<?php

namespace Tests;

use App\Models\Quote;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use App\Services\QuoteService;
use App\Services\RfqService;
use App\Support\Perm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Perm::flush();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create()->fresh();
    }

    protected function buyer(): User
    {
        return User::factory()->create()->fresh();
    }

    /** An active supplier company with one user. */
    protected function supplierUser(array $supplier = []): User
    {
        return User::factory()->supplier(Supplier::factory()->create($supplier))->create()->fresh();
    }

    /** A published request with two items (10 pcs, 2.5 t) and the given suppliers invited. */
    protected function openRfq(User $buyer, array $suppliers, array $attrs = []): Rfq
    {
        $rfq = Rfq::factory()->open()->withItems()->create(['buyer_id' => $buyer->id] + $attrs);
        foreach ($suppliers as $s) {
            $rfq->invitations()->create(['supplier_id' => $s instanceof User ? $s->supplier_id : $s->id]);
        }

        return $rfq->fresh()->load('items');
    }

    /** Submit a quotation as the supplier user. $prices are unit prices in item order (null = not quoted). */
    protected function quote(Rfq $rfq, User $supplierUser, array $prices, array $data = []): Quote
    {
        Auth::setUser($supplierUser);
        $map = [];
        foreach ($rfq->items as $i => $item) {
            $map[$item->id] = $prices[$i] ?? null;
        }

        return app(QuoteService::class)->submit($rfq, $supplierUser->supplier, $supplierUser, $data + ['currency' => 'USD', 'delivery_days' => 10, 'payment_terms' => 'Net 30', 'valid_until' => now()->addDays(30)->toDateString(), 'notes' => null, 'prices' => $map]);
    }

    protected function closeRfq(Rfq $rfq): Rfq
    {
        Auth::setUser($rfq->buyer);

        return app(RfqService::class)->close($rfq)->refresh()->load('items');
    }
}
