<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Support\Money;

/**
 * Three rule-based helpers. They read text and data and make a proposal; they never save, send or award anything.
 * There is no language model behind them.
 */
class AssistantService
{
    private const UNITS = ['t' => ['t', 'ton', 'tons', 'tonne', 'tonnes'], 'kg' => ['kg', 'kilo', 'kilos', 'kilogram', 'kilograms'], 'pcs' => ['pcs', 'pc', 'piece', 'pieces', 'units', 'unit', 'x'], 'm' => ['m', 'meter', 'meters', 'metre', 'metres'], 'm2' => ['m2', 'sqm'], 'm3' => ['m3', 'cbm'],
        'bag' => ['bag', 'bags'], 'box' => ['box', 'boxes'], 'set' => ['set', 'sets'], 'l' => ['l', 'liter', 'liters', 'litre', 'litres'], 'h' => ['h', 'hour', 'hours'], 'months' => ['month', 'months'], 'sheets' => ['sheet', 'sheets']];

    /**
     * Turn a sentence such as "40 t rebar 16 mm and 200 sheets welded mesh for the bridge deck, needed in 3 weeks"
     * into a title, items and a suggested deadline.
     *
     * @return array{title:string,items:array<int,array{name:string,quantity:string,unit:string}>,deadline_days:int,needed_days:int,category_id:?int,notes:array<int,string>}
     */
    public function draftFromSentence(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $notes = [];
        $days = 14;
        $neededDays = 30;
        if (preg_match('/\b(?:in|within)\s+(\d{1,3})\s*(day|days|week|weeks|month|months)\b/iu', $text, $m)) {
            $n = (int) $m[1] * (str_starts_with(mb_strtolower($m[2]), 'week') ? 7 : (str_starts_with(mb_strtolower($m[2]), 'month') ? 30 : 1));
            $neededDays = $n;
            $days = max(3, min(60, intdiv($n, 2)));
            $notes[] = "You need it in {$n} days, so the quotation deadline is set {$days} days from now.";
            $text = trim(str_replace($m[0], '', $text));
        }
        $text = trim(preg_replace('/\s*,?\s*\b(?:needed|required|due)\b\s*/iu', ' ', $text));
        $budget = '';
        if (preg_match('/\bbudget\s+(\d+(?:[.,]\d+)?)\s*([km])?\b/iu', $text, $m)) {
            $budget = Money::input(Money::parse(str_replace(',', '.', $m[1])) * (mb_strtolower($m[2] ?? '') === 'm' ? 1000000 : (mb_strtolower($m[2] ?? '') === 'k' ? 1000 : 1)));
            $text = trim(str_replace($m[0], '', $text));
        }
        $location = '';
        if (preg_match('/\bfor\s+([\p{L}][^,.;]+?)(?=\s+(?:within|in|budget)|,|\.|;|$)/iu', $text, $m)) $location = trim($m[1]);
        $purpose = '';
        if (preg_match('/\bfor\s+(?:the\s+)?([^,.;]+?)(?:,|\.|;|$|\bneeded\b|\bby\b)/iu', $text, $m)) {
            $purpose = trim($m[1]);
            $text = trim(str_replace($m[0], ' ', $text));
        }
        $alt = implode('|', array_map(fn ($w) => preg_quote($w, '/'), array_merge(...array_values(self::UNITS))));
        $items = [];
        foreach (preg_split('/\s*(?:,|;|\band\b|\bplus\b|\+)\s*/iu', $text, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (! preg_match('/^(?:about\s+|approx\.?\s+|we need\s+|need\s+|i need\s+)?(\d[\d\s.,]*)\s*(' . $alt . ')?\b\.?\s+(?:of\s+)?(.{2,})$/iu', trim($part), $m)) {
                continue;
            }
            $unit = 'pcs';
            foreach (self::UNITS as $code => $words) {
                if ($m[2] !== '' && in_array(mb_strtolower($m[2]), $words, true)) {
                    $unit = $code;
                }
            }
            try {
                $qty = Money::parseQty(str_replace(' ', '', rtrim($m[1], ' .,')));
            } catch (\InvalidArgumentException) {
                continue;
            }
            if ($qty && $qty > 0) {
                $items[] = ['name' => ucfirst(trim($m[3], ' .')), 'quantity' => Money::qtyInput($qty), 'unit' => $unit];
            }
        }
        if (! $items) {
            $notes[] = 'No quantities were found. Write each item as a number, a unit and a name, for example "40 t rebar 16 mm".';
        }
        $first = $items[0]['name'] ?? 'New request';
        $title = mb_strimwidth($first . (count($items) > 1 ? ' and ' . (count($items) - 1) . ' more' : '') . ($purpose !== '' ? ' - ' . ucfirst($purpose) : ''), 0, 160, '');
        $category = null;
        $hay = mb_strtolower($text . ' ' . $purpose);
        foreach (Category::whereNotNull('parent_id')->orWhereDoesntHave('children')->get() as $c) {
            foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($c->name), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if (mb_strlen($word) >= 4 && str_contains($hay, mb_substr($word, 0, mb_strlen($word) - 1))) {
                    $category = $c;
                    break 2;
                }
            }
        }
        if ($category) {
            $notes[] = "Category guessed from your words: {$category->name}.";
        }

        return ['title' => $title, 'items' => $items, 'deadline_days' => $days, 'needed_days' => $neededDays, 'category_id' => $category?->id, 'budget' => $budget, 'delivery_location' => $location, 'notes' => $notes];
    }

    /** Plain sentences about the quotations on a request. @return array<int, array{kind:string,text:string}> */
    public function reviewQuotations(Rfq $rfq, QuoteEvaluator $evaluator, AwardService $awards): array
    {
        $rows = $evaluator->evaluate($rfq);
        if ($rows->isEmpty()) {
            return [['kind' => 'info', 'text' => 'No quotations have arrived on this request yet.']];
        }
        $out = [];
        $n = $rfq->items()->count();
        $quotes = $rfq->liveQuotes()->with(['supplier.documents', 'supplier.scorecards', 'supplier.actions', 'items'])->get();
        $out[] = ['kind' => 'ok', 'text' => "{$rows->count()} quotation(s) received; " . $rows->filter(fn ($r) => $r['complete'])->count() . ' cover every item.'];
        $full = $rows->filter(fn ($r) => $r['complete']);
        $currencies = $quotes->pluck('currency')->unique()->values();
        if ($currencies->count() > 1) {
            $out[] = ['kind' => 'warn', 'text' => 'Quotations use different currencies (' . $currencies->join(', ') . '). Align currencies before comparing totals or the budget.'];
        } elseif ($full->isNotEmpty()) {
            $full = $full->sortBy(fn ($r) => $r['quote']->total_cents)->values();
            $lowest = $full->first()['quote'];
            $out[] = ['kind' => 'ok', 'text' => 'Lowest complete quotation: ' . $lowest->supplier->name . ' at ' . Money::format($lowest->total_cents, $lowest->currency) . '.'];
            if ($rfq->budget_cents) {
                $difference = $rfq->budget_cents - $lowest->total_cents;
                $out[] = ['kind' => $difference >= 0 ? 'ok' : 'bad', 'text' => 'The lowest complete quotation is ' . Money::format(abs($difference), $rfq->currency) . ($difference >= 0 ? ' under' : ' over') . ' the budget of ' . Money::format($rfq->budget_cents, $rfq->currency) . '.'];
            }
        }
        if ($currencies->count() === 1 && $full->count() >= 2) {
            $min = $full->first()['quote']->total_cents;
            $next = $full->get(1)['quote'];
            $max = $full->last()['quote']->total_cents;
            $spread = $min > 0 ? intdiv(($max - $min) * 100, $min) : 0;
            $nextDifference = $min > 0 ? intdiv(($next->total_cents - $min) * 100, $min) : 0;
            $out[] = ['kind' => $spread > 25 ? 'warn' : 'info', 'text' => 'Next lowest: ' . $next->supplier->name . ' at ' . Money::format($next->total_cents, $next->currency) . " ({$nextDifference}% higher). The spread from lowest to highest is {$spread}%" . ($spread > 25 ? '; check that suppliers understood the same scope.' : '.')];
        }
        foreach ($rows->reject(fn ($r) => $r['complete']) as $r) {
            $out[] = ['kind' => 'warn', 'text' => "{$r['quote']->supplier->name} priced {$r['coverage']} of {$n} items and cannot be awarded alone."];
        }
        foreach ($rows->filter(fn ($r) => $r['quote']->isExpired()) as $r) {
            $out[] = ['kind' => 'bad', 'text' => "The quotation from {$r['quote']->supplier->name} is no longer valid (until {$r['quote']->valid_until->format('j M Y')})."];
        }
        if ($rows->count() === 1) {
            $out[] = ['kind' => 'warn', 'text' => 'Only one supplier quoted. Consider a best-and-final round or inviting more suppliers before awarding.'];
        }
        $fast = $rows->sortBy(fn ($r) => $r['quote']->delivery_days)->first();
        $out[] = ['kind' => 'info', 'text' => "Fastest delivery: {$fast['quote']->supplier->name}, {$fast['quote']->delivery_days} days."];
        $risk = app(SupplierInsightService::class);
        foreach ($quotes as $quote) {
            $level = $risk->risk($quote->supplier);
            if ($level['level'] === 'high') {
                $out[] = ['kind' => 'bad', 'text' => $quote->supplier->name . ' has high supplier risk' . ($level['reasons'] ? ': ' . $level['reasons'][0] : '.')];
            }
        }

        return $out;
    }

    /** Unit prices more than 20% from their item median, with no float arithmetic. */
    public function unusualPrices(Rfq $rfq): array
    {
        // Values in different currencies cannot share a median until an exchange rate is chosen.
        $quotes = $rfq->liveQuotes()->where('currency', $rfq->currency)->with(['supplier', 'items'])->get(); $out = [];
        foreach ($rfq->items as $item) {
            $lines = $quotes->map(fn ($q) => [$q, $q->items->firstWhere('rfq_item_id', $item->id)])->filter(fn ($x) => $x[1]?->unit_price_cents !== null)->sortBy(fn ($x) => $x[1]->unit_price_cents)->values();
            if ($lines->count() < 3) continue;
            $median = $lines[intdiv($lines->count(), 2)][1]->unit_price_cents;
            foreach ($lines as [$quote, $line]) { $delta = $line->unit_price_cents - $median; if (abs($delta) * 100 > $median * 20) $out[] = ['supplier' => $quote->supplier->name, 'item' => $item->name, 'price' => $line->unit_price_cents, 'median' => $median, 'difference' => intdiv($delta * 100, $median), 'check' => $delta > 0 ? 'Room to negotiate' : 'Possible mistake or missing scope']; }
        }
        return $out;
    }

    /** Active suppliers for a category, best first, each with the reasons for its place. */
    public function suggestSuppliers(int $categoryId): array
    {
        return Supplier::where('status', 'active')->where('category_id', $categoryId)->withCount(['invitations', 'purchaseOrders', 'quotes as quoted' => fn ($q) => $q->whereIn('status', ['submitted', 'won', 'lost'])])->with('documents', 'scorecards', 'actions')->get()
            ->map(function (Supplier $s) {
                $risk = app(SupplierInsightService::class)->risk($s);
                $resp = $s->invitations_count ? min(100, intdiv($s->quoted * 100, $s->invitations_count)) : null;
                $points = $s->rating_tenths * 2 + ($resp ?? 50) / 2 + min(20, $s->purchase_orders_count * 5) - $risk['points'];
                $why = array_filter(['Rated ' . $s->rating() . ' of 5', $resp === null ? 'Never invited before' : "Answers {$resp}% of invitations", $s->purchase_orders_count ? "{$s->purchase_orders_count} order(s) won" : null, ucfirst($risk['level']) . ' risk']);

                return ['supplier' => $s, 'points' => (int) round($points), 'why' => array_values($why), 'risk' => $risk['level']];
            })->sortByDesc('points')->values()->all();
    }
}
