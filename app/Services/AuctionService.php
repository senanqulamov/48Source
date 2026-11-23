<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Auction;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Reverse auction on a closed request: suppliers lower their totals against each other for a short, fixed time. */
class AuctionService
{
    /** Complete, valid quotations that may take part. */
    public function eligible(Rfq $rfq)
    {
        $n = $rfq->items()->count();

        return $rfq->quotes()->where('status', 'submitted')->with('supplier', 'items')->get()->filter(fn (Quote $q) => $q->coverage() === $n && ! $q->isExpired() && $q->supplier->isActive())->values();
    }

    public function open(Rfq $rfq, User $by, int $minutes = 30, int $stepBp = 50): Auction
    {
        if ($rfq->status !== 'closed') {
            throw new BusinessRule('An auction can only run on a request that is closed and not yet awarded.');
        }
        if ($rfq->auction()->exists()) {
            throw new BusinessRule('This request already had an auction.');
        }
        if ($rfq->openBafo()) {
            throw new BusinessRule('A best-and-final round is still open on this request.');
        }
        if ($minutes < 5 || $minutes > 240) {
            throw new BusinessRule('The auction lasts between 5 and 240 minutes.');
        }
        if ($stepBp < 1 || $stepBp > 1000) {
            throw new BusinessRule('The smallest step is between 0.01% and 10%.');
        }
        $quotes = $this->eligible($rfq);
        if ($quotes->count() < 2) {
            throw new BusinessRule('An auction needs at least two complete, valid quotations.');
        }
        if ($quotes->pluck('currency')->unique()->count() > 1) {
            throw new BusinessRule('The quotations are in different currencies and cannot be auctioned against each other.');
        }
        $a = Auction::create(['rfq_id' => $rfq->id, 'opened_by' => $by->id, 'starts_at' => now(), 'ends_at' => now()->addMinutes($minutes), 'min_step_bp' => $stepBp]);
        foreach ($quotes as $q) {
            Notifier::supplier($q->supplier_id, "A reverse auction started on {$rfq->number}. It ends at {$a->ends_at->format('H:i')}. You can lower your total until then.", route('rfqs.auction', $rfq), 'Auction');
        }
        Audit::log('Auction', "Started a {$minutes}-minute reverse auction with {$quotes->count()} suppliers", $rfq, $rfq->number);

        return $a->fresh();
    }

    /** Current standing: supplier id => ['total' => cents, 'at' => time of the last change, 'bids' => count], best first. */
    public function standing(Auction $a): array
    {
        $rows = [];
        foreach ($this->eligible($a->rfq) as $q) {
            $rows[$q->supplier_id] = ['total' => $q->total_cents, 'at' => $a->starts_at->timestamp, 'bids' => 0, 'start' => $q->total_cents, 'name' => $q->supplier->name];
        }
        foreach ($a->bids()->orderBy('id')->get() as $b) {
            if (isset($rows[$b->supplier_id])) {
                $rows[$b->supplier_id]['total'] = $b->total_cents;
                $rows[$b->supplier_id]['at'] = $b->created_at->timestamp * 1000000 + $b->id;
                $rows[$b->supplier_id]['bids']++;
            }
        }
        uasort($rows, fn ($x, $y) => [$x['total'], $x['at']] <=> [$y['total'], $y['at']]);

        return $rows;
    }

    /** The highest total this supplier may bid now. */
    public function ceilingFor(Auction $a, int $supplierId): ?int
    {
        $own = $this->standing($a)[$supplierId]['total'] ?? null;

        return $own === null ? null : $own - max(1, intdiv($own * $a->min_step_bp + 9999, 10000));
    }

    public function bid(Auction $a, User $user, string $amount): void
    {
        if ($user->role !== 'supplier' || ! $user->supplier_id) {
            throw new BusinessRule('Only suppliers can bid.');
        }
        try {
            $total = Money::parse($amount);
        } catch (InvalidArgumentException) {
            throw new BusinessRule('The bid is not a valid amount.');
        }
        if ($total === null || $total <= 0) {
            throw new BusinessRule('Enter your new total.');
        }
        DB::transaction(function () use ($a, $user, $total) {
            $a = Auction::whereKey($a->id)->lockForUpdate()->first();
            if (! $a->isLive()) {
                throw new BusinessRule('The auction has ended.');
            }
            $max = $this->ceilingFor($a, $user->supplier_id) ?? throw new BusinessRule('Your company does not take part in this auction.');
            if ($total > $max) {
                throw new BusinessRule('Your new total must be ' . Money::format($max, $a->rfq->currency) . ' or lower: at least ' . rtrim(rtrim(number_format($a->min_step_bp / 100, 2), '0'), '.') . '% below your current total.');
            }
            $a->bids()->create(['supplier_id' => $user->supplier_id, 'user_id' => $user->id, 'total_cents' => $total]);
            if ($a->ends_at->lte(now()->addMinutes($a->extend_minutes))) {
                $a->update(['ends_at' => now()->addMinutes($a->extend_minutes)]);
            }
        });
        Audit::log('Auction', 'Placed a bid', $a->rfq, $a->rfq->number, supplierId: $user->supplier_id);
    }

    /** Close the auction and write each supplier's final total into its quotation, spread over the lines in proportion. */
    public function end(Auction $a): Auction
    {
        if ($a->status !== 'live') {
            throw new BusinessRule('This auction has already ended.');
        }

        return DB::transaction(function () use ($a) {
            $standing = $this->standing($a);
            foreach ($this->eligible($a->rfq) as $q) {
                $row = $standing[$q->supplier_id] ?? null;
                if (! $row || ! $row['bids'] || $row['total'] === $q->total_cents) {
                    continue;
                }
                $this->rescale($q, $row['total']);
            }
            $a->update(['status' => 'ended', 'ends_at' => min($a->ends_at, now()), 'applied_at' => now()]);
            $best = array_key_first($standing);
            Notifier::send($a->rfq->buyer, "The auction on {$a->rfq->number} ended. Best total: " . Money::format($standing[$best]['total'], $a->rfq->currency) . " from {$standing[$best]['name']}.", route('rfqs.compare', $a->rfq), 'Auction');
            foreach (array_keys($standing) as $sid) {
                Notifier::supplier($sid, "The auction on {$a->rfq->number} ended. Your final total was recorded on your quotation.", route('quotes.form', $a->rfq), 'Auction');
            }
            Audit::log('Auction', 'Auction ended, final totals written to the quotations', $a->rfq, $a->rfq->number);

            return $a->fresh();
        });
    }

    /** Spread a new total over the quotation lines with whole cents; the last line takes the remainder so the sum is exact. */
    public function rescale(Quote $q, int $newTotal): void
    {
        $lines = $q->items()->with('rfqItem')->whereNotNull('unit_price_cents')->orderBy('id')->get();
        $old = (int) $lines->sum('total_cents');
        if ($old <= 0 || $lines->isEmpty()) {
            return;
        }
        $left = $newTotal;
        foreach ($lines as $i => $line) {
            $t = $i === $lines->count() - 1 ? $left : intdiv($line->total_cents * $newTotal + intdiv($old, 2), $old);
            $left -= $t;
            $qty = max(1, $line->rfqItem->quantity_milli);
            $line->update(['total_cents' => $t, 'unit_price_cents' => max(1, intdiv($t * 1000 + intdiv($qty, 2), $qty))]);
        }
        $q->update(['total_cents' => $newTotal, 'revision' => $q->revision + 1]);
    }

    public function endExpired(): int
    {
        $n = 0;
        foreach (Auction::where('status', 'live')->where('ends_at', '<=', now())->get() as $a) {
            $this->end($a);
            $n++;
        }

        return $n;
    }
}
