<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\FeatureFlag;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\Sequence;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AwardService
{
    public const MIN_JUSTIFICATION = 15;

    /** Reasons the request as a whole cannot be awarded yet. */
    public function rfqBlockers(Rfq $rfq): array
    {
        $out = [];
        if ($rfq->status === 'awarded') {
            return ['This request is already awarded.'];
        }
        if ($rfq->status !== 'closed') {
            $out[] = 'Close the request to quotations before awarding.';
        }
        if ($rfq->openBafo()) {
            $out[] = 'A best-and-final round is still open. Wait for its deadline or close it.';
        }
        if ($rfq->auction()->where('status', 'live')->exists()) {
            $out[] = 'A reverse auction is still running.';
        }
        $quotes = $rfq->liveQuotes()->get();
        if ($quotes->isEmpty()) {
            $out[] = 'There are no quotations to award.';
        }
        if ($quotes->pluck('currency')->unique()->count() > 1) {
            $out[] = 'Quotations use different currencies ('.$quotes->pluck('currency')->unique()->implode(', ').'). Mixed currencies cannot be compared or awarded.';
        }

        return $out;
    }

    /** Lowest total among quotations that price every item. */
    public function lowestComplete(Rfq $rfq): ?Quote
    {
        $n = $rfq->items()->count();

        return $rfq->liveQuotes()->with('items')->get()->filter(fn (Quote $q) => $q->coverage() === $n)->sortBy('total_cents')->first();
    }

    public function needsJustification(Rfq $rfq, Quote $quote): bool
    {
        $low = $this->lowestComplete($rfq);

        return $low !== null && $quote->total_cents > $low->total_cents;
    }

    /** Award the whole request to one quotation and raise a draft purchase order. */
    public function award(Rfq $rfq, Quote $quote, User $by, ?string $justification = null): PurchaseOrder
    {
        if ($quote->rfq_id !== $rfq->id || $quote->status !== 'submitted') {
            throw new BusinessRule('This quotation cannot be awarded.');
        }
        if ($blockers = $this->rfqBlockers($rfq)) {
            throw new BusinessRule($blockers[0]);
        }
        $quote->load('items', 'supplier');
        if ($quote->coverage() < $rfq->items()->count()) {
            throw new BusinessRule('This quotation does not price every item, so it cannot be awarded the whole request.');
        }
        if ($quote->isExpired()) {
            throw new BusinessRule('This quotation expired on '.$quote->valid_until->format('j M Y').'. Ask the supplier to extend it.');
        }
        if (! $quote->supplier->isActive()) {
            throw new BusinessRule('The supplier is not active and cannot be awarded.');
        }
        $justification = trim((string) $justification);
        if ($this->needsJustification($rfq, $quote) && mb_strlen($justification) < self::MIN_JUSTIFICATION) {
            throw new BusinessRule('This is not the lowest complete quotation. Explain the choice in at least '.self::MIN_JUSTIFICATION.' characters.');
        }

        return DB::transaction(function () use ($rfq, $quote, $justification) {
            $po = $this->raise($rfq, $quote, $quote->items->whereNotNull('unit_price_cents'), $justification ?: null);
            $rfq->liveQuotes()->where('id', '!=', $quote->id)->update(['status' => 'lost']);
            $quote->update(['status' => 'won']);
            $rfq->update(['status' => 'awarded', 'awarded_at' => now()]);
            $this->announce($rfq, collect([$po]));
            Audit::log('Award', "Awarded to {$quote->supplier->name}: {$po->number}", $rfq, supplierId: $quote->supplier_id);

            return $po;
        });
    }

    /**
     * The cheapest supplier for every item, grouped by supplier.
     *
     * @return Collection<int, array{quote:Quote,lines:Collection,total:int}> keyed by supplier id
     */
    public function splitPlan(Rfq $rfq): Collection
    {
        $quotes = $rfq->liveQuotes()->with('items', 'supplier')->get()->filter(fn (Quote $q) => ! $q->isExpired() && $q->supplier->isActive());
        $plan = collect();
        foreach ($rfq->items as $item) {
            $best = null;
            foreach ($quotes as $q) {
                $line = $q->items->firstWhere('rfq_item_id', $item->id);
                if ($line && $line->unit_price_cents !== null && ($best === null || $line->unit_price_cents < $best[1]->unit_price_cents
                    || ($line->unit_price_cents === $best[1]->unit_price_cents && $q->submitted_at < $best[0]->submitted_at))) {
                    $best = [$q, $line];
                }
            }
            if ($best === null) {
                throw new BusinessRule("No valid quotation prices \"{$item->name}\". The request cannot be split.");
            }
            $row = $plan->get($best[0]->supplier_id, ['quote' => $best[0], 'lines' => collect(), 'total' => 0]);
            $row['lines']->push($best[1]);
            $row['total'] += $best[1]->total_cents;
            $plan->put($best[0]->supplier_id, $row);
        }

        return $plan;
    }

    /** Award item by item: one draft purchase order per winning supplier. */
    public function splitAward(Rfq $rfq, User $by): Collection
    {
        if (! FeatureFlag::isOn('split_awards')) {
            throw new BusinessRule('Split awards are switched off.');
        }
        if ($blockers = $this->rfqBlockers($rfq)) {
            throw new BusinessRule($blockers[0]);
        }
        $plan = $this->splitPlan($rfq);

        return DB::transaction(function () use ($rfq, $plan) {
            $orders = $plan->map(fn (array $row) => $this->raise($rfq, $row['quote'], $row['lines'], 'Awarded item by item to the lowest price.'))->values();
            $rfq->liveQuotes()->whereIn('supplier_id', $plan->keys())->update(['status' => 'won']);
            $rfq->quotes()->where('status', 'submitted')->update(['status' => 'lost']);
            $rfq->update(['status' => 'awarded', 'awarded_at' => now()]);
            $this->announce($rfq, $orders);
            Audit::log('Award', 'Split award: '.$orders->pluck('number')->implode(', '), $rfq);

            return $orders;
        });
    }

    private function raise(Rfq $rfq, Quote $quote, Collection $lines, ?string $justification): PurchaseOrder
    {
        $items = $rfq->items->keyBy('id');
        $po = PurchaseOrder::create([
            'number' => Sequence::next('PO'),
            'rfq_id' => $rfq->id,
            'supplier_id' => $quote->supplier_id,
            'quote_id' => $quote->id,
            'buyer_id' => $rfq->buyer_id,
            'status' => 'draft',
            'currency' => $quote->currency,
            'total_cents' => $lines->sum('total_cents'),
            'payment_terms' => $quote->payment_terms ?? $rfq->payment_terms,
            'delivery_location' => $rfq->delivery_location,
            'expected_at' => now()->addDays((int) $quote->delivery_days)->toDateString(),
            'award_justification' => $justification,
        ]);
        foreach ($lines as $line) {
            $item = $items[$line->rfq_item_id];
            $po->items()->create(['rfq_item_id' => $item->id, 'name' => $item->name, 'quantity_milli' => $item->quantity_milli, 'unit' => $item->unit, 'unit_price_cents' => $line->unit_price_cents, 'total_cents' => $line->total_cents]);
        }

        return $po;
    }

    private function announce(Rfq $rfq, Collection $orders): void
    {
        $winners = $orders->pluck('supplier_id')->all();
        foreach ($winners as $sid) {
            Notifier::supplier($sid, "You won {$rfq->number}. A purchase order is being prepared.", route('orders.index'), 'Award');
        }
        foreach ($rfq->quotes()->where('status', 'lost')->pluck('supplier_id') as $sid) {
            Notifier::supplier($sid, "{$rfq->number} was awarded to another supplier. Thank you for quoting.", route('quotes.invitations'), 'Award');
        }
    }
}
