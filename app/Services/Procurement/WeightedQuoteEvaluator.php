<?php

namespace App\Services\Procurement;

use InvalidArgumentException;

/**
 * Pure quote evaluation. Read-only: does not award an RFQ or create orders.
 * Scores are advisory; commercial/approval policy belongs to an audited award service.
 */
final class WeightedQuoteEvaluator
{
    public const PRESETS = [
        'balanced' => ['price' => 40, 'delivery' => 20, 'assurance' => 25, 'performance' => 15],
        'price' => ['price' => 60, 'delivery' => 10, 'assurance' => 20, 'performance' => 10],
        'schedule' => ['price' => 25, 'delivery' => 40, 'assurance' => 20, 'performance' => 15],
    ];

    /**
     * @param array<int,array{id:int,quantity:float|int}> $items
     * @param array<int,array{id:int,lines:array<int,float|int|null>,delivery_days:int,assurance:float|int,performance:float|int}> $quotes
     */
    public function evaluate(array $items, array $quotes, string $preset = 'balanced'): array
    {
        if (! isset(self::PRESETS[$preset])) {
            throw new InvalidArgumentException('Unknown quote-comparison preset.');
        }

        if ($items === [] || $quotes === []) {
            return ['preset' => $preset, 'weights' => self::PRESETS[$preset], 'line_lowest' => [], 'quotes' => [], 'summary' => null];
        }

        $quantities = [];
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $qty = (float) $item['quantity'];
            if ($id <= 0 || ! is_finite($qty) || $qty <= 0 || isset($quantities[$id])) {
                throw new InvalidArgumentException('Items need distinct IDs and positive quantities.');
            }
            $quantities[$id] = $qty;
        }

        $minimums = array_fill_keys(array_keys($quantities), null);
        $normalized = [];
        foreach ($quotes as $quote) {
            $lines = [];
            $amount = 0.0;
            foreach ($quantities as $id => $qty) {
                $raw = $quote['lines'][$id] ?? null;
                if ($raw === null || $raw === '') {
                    $lines[$id] = null;
                    continue;
                }
                if (! is_numeric($raw) || ! is_finite((float) $raw) || (float) $raw <= 0) {
                    throw new InvalidArgumentException('Quoted unit prices must be positive finite numbers.');
                }
                $price = (float) $raw;
                $lines[$id] = $price;
                $amount += $price * $qty;
                $minimums[$id] = $minimums[$id] === null ? $price : min($minimums[$id], $price);
            }
            $delivery = (int) $quote['delivery_days'];
            $assurance = (float) $quote['assurance'];
            $performance = (float) $quote['performance'];
            if ($delivery < 1 || ! is_finite($assurance) || ! is_finite($performance)
                || $assurance < 0 || $assurance > 100 || $performance < 0 || $performance > 100) {
                throw new InvalidArgumentException('Invalid delivery days, assurance or performance score.');
            }
            $normalized[] = [
                'id' => (int) $quote['id'],
                'lines' => $lines,
                'total' => round($amount, 2),
                'coverage' => count(array_filter($lines, static fn ($value) => $value !== null)),
                'delivery_days' => $delivery,
                'assurance' => $assurance,
                'performance' => $performance,
            ];
        }

        $fastest = min(array_column($normalized, 'delivery_days'));
        $weights = self::PRESETS[$preset];

        foreach ($normalized as &$q) {
            $reference = 0.0;
            $diffs = [];
            foreach ($quantities as $id => $qty) {
                if ($q['lines'][$id] === null) {
                    $diffs[$id] = null;
                    continue;
                }
                $reference += $minimums[$id] * $qty;
                $diffs[$id] = round(($q['lines'][$id] / $minimums[$id] - 1) * 100, 2);
            }
            $coverageRatio = $q['coverage'] / count($quantities);
            $priceScore = $q['total'] > 0 ? ($reference / $q['total']) * 100 * $coverageRatio : 0;
            $deliveryScore = $fastest / $q['delivery_days'] * 100;
            $q['score'] = round((
                $priceScore * $weights['price'] +
                $deliveryScore * $weights['delivery'] +
                $q['assurance'] * $weights['assurance'] +
                $q['performance'] * $weights['performance']
            ) / 100, 2);
            $q['complete'] = $q['coverage'] === count($quantities);
            $q['line_difference_percent'] = $diffs;
        }
        unset($q);

        // Never recommend a partial quote as a complete-RFQ award.
        $complete = array_values(array_filter($normalized, static fn ($q) => $q['complete']));
        $bestValue = $complete;
        usort($bestValue, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $lowest = $complete;
        usort($lowest, static fn ($a, $b) => $a['total'] <=> $b['total']);
        $speed = $complete;
        usort($speed, static fn ($a, $b) => $a['delivery_days'] <=> $b['delivery_days']);

        return [
            'preset' => $preset,
            'weights' => $weights,
            'line_lowest' => $minimums,
            'quotes' => $normalized,
            'summary' => [
                'best_value_id' => $bestValue[0]['id'] ?? null,
                'lowest_price_id' => $lowest[0]['id'] ?? null,
                'fastest_delivery_id' => $speed[0]['id'] ?? null,
            ],
        ];
    }
}
