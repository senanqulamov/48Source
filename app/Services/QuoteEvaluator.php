<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\Rfq;
use Illuminate\Support\Collection;

/**
 * Weighted scoring of the quotations on one request.
 * Scores are a ranking aid (0-100); they never change a monetary amount.
 */
class QuoteEvaluator
{
    public const PRESETS = [
        'balanced' => ['label' => 'Balanced', 'price' => 40, 'quality' => 20, 'delivery' => 25, 'terms' => 15],
        'price' => ['label' => 'Lowest price', 'price' => 60, 'quality' => 10, 'delivery' => 20, 'terms' => 10],
        'quality' => ['label' => 'Best quality', 'price' => 25, 'quality' => 40, 'delivery' => 20, 'terms' => 15],
    ];

    /**
     * @return Collection<int, array{quote:Quote,complete:bool,coverage:int,price:float,quality:float,delivery:float,terms:float,score:float,rank:int}>
     */
    public function evaluate(Rfq $rfq, ?string $preset = null): Collection
    {
        $w = self::PRESETS[$preset ?? $rfq->scoring_preset] ?? self::PRESETS['balanced'];
        $items = $rfq->items;
        $quotes = $rfq->liveQuotes()->with('items', 'supplier')->get();
        if ($quotes->isEmpty() || $items->isEmpty()) {
            return collect();
        }

        // lowest price offered for each item, across all quotations
        $min = [];
        foreach ($quotes as $q) {
            foreach ($q->items as $qi) {
                if ($qi->unit_price_cents !== null) {
                    $min[$qi->rfq_item_id] = min($min[$qi->rfq_item_id] ?? PHP_INT_MAX, $qi->unit_price_cents);
                }
            }
        }
        $minDays = max(1, (int) $quotes->min('delivery_days'));
        $maxTerm = max(1, $quotes->max(fn (Quote $q) => self::termDays($q->payment_terms)));

        $rows = $quotes->map(function (Quote $q) use ($items, $min, $minDays, $maxTerm, $w) {
            $byItem = $q->items->keyBy('rfq_item_id');
            $sum = 0.0;
            $covered = 0;
            foreach ($items as $item) {
                $p = $byItem[$item->id]->unit_price_cents ?? null;
                if ($p !== null && $p > 0) {
                    $sum += $min[$item->id] / $p;
                    $covered++;
                }
            }
            $price = $sum / $items->count() * 100;
            $quality = $q->supplier->rating_tenths / 50 * 100;
            $delivery = $q->delivery_days ? $minDays / $q->delivery_days * 100 : 0;
            $terms = self::termDays($q->payment_terms) / $maxTerm * 100;
            $score = ($price * $w['price'] + $quality * $w['quality'] + $delivery * $w['delivery'] + $terms * $w['terms']) / 100;

            return ['quote' => $q, 'complete' => $covered === $items->count(), 'coverage' => $covered,
                'price' => round($price, 1), 'quality' => round($quality, 1), 'delivery' => round($delivery, 1), 'terms' => round($terms, 1), 'score' => round($score, 1), 'rank' => 0];
        })->sortByDesc('score')->values();

        return $rows->map(function (array $r, int $i) {
            $r['rank'] = $i + 1;

            return $r;
        });
    }

    /** "Net 45" -> 45. Longer payment terms score better for the buyer. */
    public static function termDays(?string $terms): int
    {
        return preg_match('/(\d{1,3})/', (string) $terms, $m) ? min(120, (int) $m[1]) : 0;
    }
}
