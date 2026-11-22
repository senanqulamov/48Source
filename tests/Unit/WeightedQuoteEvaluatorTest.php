<?php

use App\Services\Procurement\WeightedQuoteEvaluator;

it('compares line prices and calculates weighted recommendations', function () {
    $items = [['id' => 10, 'quantity' => 2], ['id' => 20, 'quantity' => 1]];
    $quotes = [
        ['id' => 1, 'lines' => [10 => 100, 20 => 80], 'delivery_days' => 12, 'assurance' => 95, 'performance' => 91],
        ['id' => 2, 'lines' => [10 => 90, 20 => 100], 'delivery_days' => 8, 'assurance' => 84, 'performance' => 86],
        ['id' => 3, 'lines' => [10 => 75, 20 => null], 'delivery_days' => 5, 'assurance' => 96, 'performance' => 97],
    ];

    $result = (new WeightedQuoteEvaluator)->evaluate($items, $quotes);

    expect($result['line_lowest'][10])->toBe(75.0);
    expect($result['line_lowest'][20])->toBe(80.0);
    expect($result['quotes'][0]['line_difference_percent'][10])->toBe(33.33);
    expect($result['quotes'][2]['complete'])->toBeFalse();
    expect($result['quotes'][2]['line_difference_percent'][20])->toBeNull();
    expect($result['summary']['lowest_price_id'])->toBe(1);
    expect($result['summary']['best_value_id'])->toBeIn([1, 2]);
    expect($result['summary']['fastest_delivery_id'])->toBe(2);
    expect($result['weights'])->toBe(['price' => 40, 'delivery' => 20, 'assurance' => 25, 'performance' => 15]);
});

it('rejects negative prices and invalid preset names', function () {
    $evaluator = new WeightedQuoteEvaluator;
    expect(fn () => $evaluator->evaluate([['id' => 1, 'quantity' => 1]], [
        ['id' => 1, 'lines' => [1 => -2], 'delivery_days' => 2, 'assurance' => 50, 'performance' => 50],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $evaluator->evaluate([], [], 'unknown'))->toThrow(InvalidArgumentException::class);
});

it('does not recommend any partial quote for an entire RFQ', function () {
    $result = (new WeightedQuoteEvaluator)->evaluate(
        [['id' => 1, 'quantity' => 1], ['id' => 2, 'quantity' => 1]],
        [['id' => 5, 'lines' => [1 => 20, 2 => null], 'delivery_days' => 2, 'assurance' => 80, 'performance' => 80]]
    );
    expect($result['summary'])->toBe([
        'best_value_id' => null,
        'lowest_price_id' => null,
        'fastest_delivery_id' => null,
    ]);
});
