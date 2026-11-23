<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Support\Collection;

/** Figures for the spend, budget, savings and KPI pages. All money stays in whole cents. */
class ReportService
{
    /** Orders that count as committed spend: issued or later, not cancelled. */
    private function orders(User $user, int $year)
    {
        return PurchaseOrder::visibleTo($user)->whereNotIn('status', ['draft', 'cancelled'])->whereYear('created_at', $year);
    }

    /** @return array{total:int,count:int,months:array<int,int>,byCategory:Collection,bySupplier:Collection} */
    public function spend(User $user, int $year): array
    {
        $orders = $this->orders($user, $year)->with('supplier:id,name', 'rfq:id,category_id', 'rfq.category:id,name')->get();
        $months = array_fill(1, 12, 0);
        foreach ($orders as $o) {
            $months[(int) $o->created_at->format('n')] += $o->total_cents;
        }
        $group = fn (callable $key) => $orders->groupBy($key)->map(fn ($g, $k) => ['name' => $k, 'cents' => (int) $g->sum('total_cents'), 'orders' => $g->count()])->sortByDesc('cents')->values();

        return ['total' => (int) $orders->sum('total_cents'), 'count' => $orders->count(), 'months' => $months,
            'byCategory' => $group(fn ($o) => $o->rfq->category?->name ?? 'No category'), 'bySupplier' => $group(fn ($o) => $o->supplier->name)];
    }

    /** Budget against committed spend for each category that has a budget. */
    public function budgets(User $user, int $year): Collection
    {
        $spent = $this->orders($user, $year)->with('rfq:id,category_id')->get(['id', 'rfq_id', 'total_cents'])->groupBy(fn ($o) => $o->rfq->category_id)->map(fn ($g) => (int) $g->sum('total_cents'));

        return Budget::where('year', $year)->with('category')->get()->map(function (Budget $b) use ($spent) {
            $used = (int) ($spent[$b->category_id] ?? 0);

            return ['budget' => $b, 'name' => $b->category->name, 'amount' => $b->amount_cents, 'used' => $used, 'left' => $b->amount_cents - $used, 'pct' => $b->amount_cents > 0 ? (int) min(999, intdiv($used * 100, $b->amount_cents)) : 0];
        })->sortByDesc('pct')->values();
    }

    public function setBudget(int $year, int $categoryId, ?int $cents): void
    {
        if ($year < 2000 || $year > 2100 || ! Category::whereKey($categoryId)->exists()) {
            throw new \App\Exceptions\BusinessRule('Choose a category and a year.');
        }
        if ($cents === null || $cents <= 0) {
            throw new \App\Exceptions\BusinessRule('Enter a budget above zero.');
        }
        Budget::updateOrCreate(['year' => $year, 'category_id' => $categoryId], ['amount_cents' => $cents]);
        \App\Support\Audit::log('Budget', "Set {$year} budget for category #{$categoryId}");
    }

    /**
     * Savings on awarded requests of the year, measured two ways: against the budget, and against the average complete quotation.
     *
     * @return array{rows:Collection,vsBudget:int,vsAverage:int,awarded:int}
     */
    public function savings(User $user, int $year): array
    {
        $rows = Rfq::visibleTo($user)->where('status', 'awarded')->whereYear('awarded_at', $year)->with('items', 'quotes.items', 'purchaseOrders')->get()->map(function (Rfq $r) {
            $awarded = (int) $r->purchaseOrders->where('status', '!=', 'cancelled')->sum('total_cents');
            $full = $r->quotes->whereIn('status', ['won', 'lost', 'submitted'])->filter(fn ($q) => $q->coverage() === $r->items->count());
            $avg = $full->count() ? intdiv((int) $full->sum('total_cents'), $full->count()) : null;

            return ['rfq' => $r, 'awarded' => $awarded, 'budget' => $r->budget_cents, 'vsBudget' => $r->budget_cents ? $r->budget_cents - $awarded : null, 'average' => $avg, 'vsAverage' => $avg !== null ? $avg - $awarded : null, 'quotes' => $full->count()];
        })->filter(fn ($x) => $x['awarded'] > 0)->sortByDesc('vsAverage')->values();

        return ['rows' => $rows, 'vsBudget' => (int) $rows->sum(fn ($x) => $x['vsBudget'] ?? 0), 'vsAverage' => (int) $rows->sum(fn ($x) => $x['vsAverage'] ?? 0), 'awarded' => (int) $rows->sum('awarded')];
    }

    /** @return array<string, array{value:string,note:string}> */
    public function kpis(User $user, int $year): array
    {
        $awarded = Rfq::visibleTo($user)->where('status', 'awarded')->whereYear('awarded_at', $year)->whereNotNull('published_at')->get(['published_at', 'awarded_at']);
        $cycle = $awarded->count() ? intdiv((int) $awarded->sum(fn ($r) => $r->published_at->diffInHours($r->awarded_at)), $awarded->count()) : null;
        $delivered = PurchaseOrder::visibleTo($user)->whereYear('created_at', $year)->whereNotNull('delivered_at')->get(['expected_at', 'delivered_at']);
        $onTime = $delivered->filter(fn ($p) => ! $p->expected_at || $p->delivered_at->startOfDay()->lte($p->expected_at))->count();
        $rfqs = Rfq::visibleTo($user)->whereYear('created_at', $year)->where('status', '!=', 'draft')->withCount('invitations', 'liveQuotes')->get();
        $invited = (int) $rfqs->sum('invitations_count');
        $invoices = Invoice::visibleTo($user)->whereYear('created_at', $year)->get(['status', 'issues']);
        $clean = $invoices->filter(fn ($i) => $i->issues === null && $i->status !== 'rejected')->count();
        $pct = fn (int $a, int $b) => $b ? intdiv($a * 100, $b) . '%' : 'n/a';

        return [
            'Request to award' => ['value' => $cycle === null ? 'n/a' : ($cycle >= 48 ? intdiv($cycle, 24) . ' days' : $cycle . ' hours'), 'note' => $awarded->count() . ' awarded requests'],
            'On-time delivery' => ['value' => $pct($onTime, $delivered->count()), 'note' => $delivered->count() . ' delivered orders'],
            'Supplier response rate' => ['value' => $pct(min($invited, (int) $rfqs->sum('live_quotes_count')), $invited), 'note' => $invited . ' invitations sent'],
            'Quotations per request' => ['value' => $rfqs->count() ? number_format($rfqs->sum('live_quotes_count') / $rfqs->count(), 1) : 'n/a', 'note' => $rfqs->count() . ' published requests'],
            'Invoices matching first time' => ['value' => $pct($clean, $invoices->count()), 'note' => $invoices->count() . ' invoices'],
        ];
    }

    /** Approved and paid invoices as journal lines for the accounting system. @return array{0:array<int,string>,1:array<int,array<int,mixed>>,2:Collection} */
    public function accounting(User $user, string $from, string $to, bool $includeExported): array
    {
        $invoices = Invoice::visibleTo($user)->whereIn('status', ['approved', 'paid'])->whereDate('issued_on', '>=', $from)->whereDate('issued_on', '<=', $to)
            ->when(! $includeExported, fn ($q) => $q->whereNull('exported_at'))->with('supplier', 'order.rfq.category')->orderBy('issued_on')->get();
        $rows = [];
        foreach ($invoices as $i) {
            $amount = number_format($i->total_cents / 100, 2, '.', '');
            $text = "{$i->supplier->name} {$i->supplier_ref} / {$i->order->number}";
            $rows[] = [$i->issued_on->format('Y-m-d'), $i->number, $i->supplier_ref, $i->supplier->name, $i->order->number, $i->order->rfq->category?->name ?? '', 'Expense', $amount, '', $i->currency, $i->due_on?->format('Y-m-d'), $i->status, $text];
            $rows[] = [$i->issued_on->format('Y-m-d'), $i->number, $i->supplier_ref, $i->supplier->name, $i->order->number, $i->order->rfq->category?->name ?? '', 'Accounts payable', '', $amount, $i->currency, $i->due_on?->format('Y-m-d'), $i->status, $text];
        }

        return [['Date', 'Document', 'Supplier invoice', 'Supplier', 'Purchase order', 'Category', 'Account', 'Debit', 'Credit', 'Currency', 'Due', 'Status', 'Text'], $rows, $invoices];
    }
}
