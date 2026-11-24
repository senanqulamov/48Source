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

    /**
     * Everything the Spend & budgets page shows for one year.
     *
     * @return array{total:int,count:int,months:array<int,int>,byCategory:Collection,bySupplier:Collection,byDepartment:Collection,covered:int,paid:int,paidCount:int,records:Collection}
     */
    public function analysis(User $user, int $year): array
    {
        $orders = $this->orders($user, $year)->with('supplier:id,name', 'buyer:id,name,department', 'rfq:id,number,category_id', 'rfq.category:id,name')->latest('id')->get();
        $months = array_fill(1, 12, 0);
        foreach ($orders as $o) {
            $months[(int) $o->created_at->format('n')] += $o->total_cents;
        }
        $group = fn (callable $key) => $orders->groupBy($key)->map(fn ($g, $k) => ['name' => (string) $k, 'cents' => (int) $g->sum('total_cents'), 'orders' => $g->count()])->sortByDesc('cents')->values();
        // "Under contract": orders placed with a supplier that holds an active contract which has not ended.
        $withContract = \App\Models\Contract::where('status', 'active')->whereDate('ends_on', '>=', now()->toDateString())->pluck('supplier_id')->unique()->all();
        $paid = \App\Models\Invoice::visibleTo($user)->where('status', 'paid')->whereYear('paid_at', $year);

        return [
            'total' => (int) $orders->sum('total_cents'), 'count' => $orders->count(), 'months' => $months,
            'byCategory' => $group(fn ($o) => $o->rfq->category?->name ?? 'No category'),
            'bySupplier' => $group(fn ($o) => $o->supplier->name),
            'byDepartment' => $group(fn ($o) => $o->buyer?->department ?: 'No department'),
            'covered' => (int) $orders->whereIn('supplier_id', $withContract)->sum('total_cents'),
            'paid' => (int) (clone $paid)->sum('total_cents'), 'paidCount' => (clone $paid)->count(),
            'records' => $orders,
        ];
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

    /**
     * The KPI reports page: the last 3, 6 or 12 months, month by month.
     *
     * @return array{labels:array<int,string>,published:array<int,int>,awarded:array<int,int>,participation:array<int,int>,volume:array<int,int>,invited:int,quoted:int,total:int,orders:int,cycle:array<int,int>,byCategory:Collection,suppliers:Collection}
     */
    public function kpiReport(User $user, int $months): array
    {
        $months = in_array($months, [3, 6, 12], true) ? $months : 12;
        $from = now()->startOfMonth()->subMonths($months - 1);
        $keys = [];
        for ($i = 0; $i < $months; $i++) {
            $keys[$from->copy()->addMonths($i)->format('Y-m')] = $from->copy()->addMonths($i)->format('M');
        }
        $zero = array_fill_keys(array_keys($keys), 0);
        $published = $awarded = $invited = $quoted = $volume = $zero;

        $rfqs = Rfq::visibleTo($user)->whereNotNull('published_at')->where('published_at', '>=', $from)->withCount('invitations', 'liveQuotes')->get(['id', 'published_at', 'awarded_at', 'status']);
        $cycle = [];
        foreach ($rfqs as $r) {
            $k = $r->published_at->format('Y-m');
            if (isset($published[$k])) {
                $published[$k]++;
                $invited[$k] += $r->invitations_count;
                $quoted[$k] += min($r->invitations_count ?: $r->live_quotes_count, $r->live_quotes_count);
            }
            if ($r->status === 'awarded' && $r->awarded_at) {
                $a = $r->awarded_at->format('Y-m');
                if (isset($awarded[$a])) {
                    $awarded[$a]++;
                }
                $cycle[] = max(0, (int) $r->published_at->diffInDays($r->awarded_at));
            }
        }
        $orders = PurchaseOrder::visibleTo($user)->whereNotIn('status', ['draft', 'cancelled'])->where('created_at', '>=', $from)->with('supplier:id,name,category_id', 'supplier.category:id,name', 'rfq:id,category_id', 'rfq.category:id,name')->get();
        foreach ($orders as $o) {
            $k = $o->created_at->format('Y-m');
            if (isset($volume[$k])) {
                $volume[$k] += $o->total_cents;
            }
        }
        $participation = [];
        foreach ($zero as $k => $_) {
            $participation[] = $invited[$k] ? (int) round($quoted[$k] * 100 / $invited[$k]) : 0;
        }

        // Supplier comparison: invitations and quotations in the period, and what each supplier was awarded.
        $rfqIds = $rfqs->pluck('id');
        $inv = \App\Models\RfqInvitation::whereIn('rfq_id', $rfqIds)->selectRaw('supplier_id, count(*) as n')->groupBy('supplier_id')->pluck('n', 'supplier_id');
        $quo = \App\Models\Quote::whereIn('rfq_id', $rfqIds)->whereIn('status', ['submitted', 'won', 'lost'])->selectRaw('supplier_id, count(*) as n')->groupBy('supplier_id')->pluck('n', 'supplier_id');
        $won = \App\Models\Quote::whereIn('rfq_id', $rfqIds)->where('status', 'won')->selectRaw('supplier_id, count(*) as n')->groupBy('supplier_id')->pluck('n', 'supplier_id');
        $value = $orders->groupBy('supplier_id')->map(fn ($g) => (int) $g->sum('total_cents'));
        $ids = $inv->keys()->merge($value->keys())->unique();
        $names = \App\Models\Supplier::whereIn('id', $ids)->with('category:id,name')->get()->keyBy('id');
        $suppliers = $ids->map(function ($id) use ($inv, $quo, $won, $value, $names) {
            $q = (int) ($quo[$id] ?? 0);

            return ['id' => $id, 'name' => $names[$id]->name ?? '-', 'category' => $names[$id]?->category?->name, 'invited' => (int) ($inv[$id] ?? 0), 'quoted' => $q,
                'win' => $q ? (int) round(((int) ($won[$id] ?? 0)) * 100 / $q) : 0, 'value' => (int) ($value[$id] ?? 0)];
        })->sortByDesc(fn ($s) => [$s['value'], $s['quoted']])->take(10)->values();

        return [
            'labels' => array_values($keys), 'published' => array_values($published), 'awarded' => array_values($awarded), 'participation' => $participation,
            'volume' => array_values($volume), 'invited' => array_sum($invited), 'quoted' => array_sum($quoted),
            'total' => (int) $orders->sum('total_cents'), 'orders' => $orders->count(), 'cycle' => $cycle,
            'byCategory' => $orders->groupBy(fn ($o) => $o->rfq->category?->name ?? 'No category')->map(fn ($g, $k) => ['name' => (string) $k, 'cents' => (int) $g->sum('total_cents')])->sortByDesc('cents')->values(),
            'suppliers' => $suppliers,
        ];
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
