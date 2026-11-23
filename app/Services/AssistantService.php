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
     * @return array{title:string,items:array<int,array{name:string,quantity:string,unit:string}>,deadline_days:int,category_id:?int,notes:array<int,string>}
     */
    public function draftFromSentence(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $notes = [];
        $days = 14;
        if (preg_match('/\b(?:in|within)\s+(\d{1,3})\s*(day|days|week|weeks|month|months)\b/iu', $text, $m)) {
            $n = (int) $m[1] * (str_starts_with(mb_strtolower($m[2]), 'week') ? 7 : (str_starts_with(mb_strtolower($m[2]), 'month') ? 30 : 1));
            $days = max(3, min(60, intdiv($n, 2)));
            $notes[] = "You need it in {$n} days, so the quotation deadline is set {$days} days from now.";
            $text = trim(str_replace($m[0], '', $text));
        }
        $text = trim(preg_replace('/\s*,?\s*\b(?:needed|required|due)\b\s*/iu', ' ', $text));
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

        return ['title' => $title, 'items' => $items, 'deadline_days' => $days, 'category_id' => $category?->id, 'notes' => $notes];
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
        $best = $rows->firstWhere('rank', 1);
        $lowest = $awards->lowestComplete($rfq);
        $out[] = ['kind' => 'ok', 'text' => "{$rows->count()} quotation(s). Best overall score: {$best['quote']->supplier->name} with " . round($best['score']) . ' points at ' . Money::format($best['quote']->total_cents, $best['quote']->currency) . '.'];
        if ($lowest && $lowest->id !== $best['quote']->id) {
            $out[] = ['kind' => 'warn', 'text' => "The lowest complete price is from {$lowest->supplier->name} (" . Money::format($lowest->total_cents, $lowest->currency) . '), ' . Money::format($best['quote']->total_cents - $lowest->total_cents, $lowest->currency) . ' below the best score. Awarding to anyone else needs a written reason.'];
        }
        $full = $rows->filter(fn ($r) => $r['complete']);
        if ($full->count() >= 2) {
            $min = $full->min(fn ($r) => $r['quote']->total_cents);
            $max = $full->max(fn ($r) => $r['quote']->total_cents);
            $spread = $min > 0 ? intdiv(($max - $min) * 100, $min) : 0;
            $out[] = ['kind' => $spread > 25 ? 'warn' : 'info', 'text' => "Complete quotations differ by {$spread}% between the lowest and the highest." . ($spread > 25 ? ' A spread this wide often means the suppliers understood the scope differently. Check the quantities and requirements.' : '')];
        }
        foreach ($rows->reject(fn ($r) => $r['complete']) as $r) {
            $out[] = ['kind' => 'warn', 'text' => "{$r['quote']->supplier->name} priced {$r['coverage']} of {$n} items and cannot be awarded alone."];
        }
        foreach ($rows->filter(fn ($r) => $r['quote']->isExpired()) as $r) {
            $out[] = ['kind' => 'bad', 'text' => "The quotation from {$r['quote']->supplier->name} is no longer valid (until {$r['quote']->valid_until->format('j M Y')})."];
        }
        if ($rfq->budget_cents && $lowest) {
            $d = $rfq->budget_cents - $lowest->total_cents;
            $out[] = ['kind' => $d >= 0 ? 'ok' : 'bad', 'text' => 'The lowest complete quotation is ' . Money::format(abs($d), $rfq->currency) . ($d >= 0 ? ' under' : ' over') . ' the budget of ' . Money::format($rfq->budget_cents, $rfq->currency) . '.'];
        }
        if ($rows->count() === 1) {
            $out[] = ['kind' => 'warn', 'text' => 'Only one supplier quoted. Consider a best-and-final round or inviting more suppliers before awarding.'];
        }
        $fast = $rows->sortBy(fn ($r) => $r['quote']->delivery_days)->first();
        $out[] = ['kind' => 'info', 'text' => "Fastest delivery: {$fast['quote']->supplier->name}, {$fast['quote']->delivery_days} days."];

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
