<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use Page;

    public function render()
    {
        $u = $this->me();
        if ($u->isSourcing()) {
            return view('livewire.dashboard-sourcing', $this->sourcing());
        }
        if ($u->role === 'supplier' && $u->supplier_id) {
            return view('livewire.dashboard-supplier', $this->supplier());
        }

        return view('livewire.dashboard-other', $this->other());
    }

    private function sourcing(): array
    {
        $u = $this->me();
        $rfqs = fn () => Rfq::visibleTo($u);
        $pos = fn () => PurchaseOrder::visibleTo($u);
        $attention = collect();
        foreach ($rfqs()->where('status', 'open')->where('deadline_at', '<=', now()->addDays(3))->orderBy('deadline_at')->withCount('liveQuotes')->get() as $r) {
            $attention->push(['sev' => 'warn', 'ref' => $r->number, 'title' => $r->title, 'why' => "Closes {$r->deadline_at->diffForHumans()} with {$r->live_quotes_count} quotation(s)", 'url' => route('rfqs.show', $r)]);
        }
        foreach ($rfqs()->where('status', 'closed')->withCount('liveQuotes')->orderBy('closed_at')->get() as $r) {
            $attention->push(['sev' => $r->live_quotes_count ? 'info' : 'bad', 'ref' => $r->number, 'title' => $r->title, 'why' => $r->live_quotes_count ? "{$r->live_quotes_count} quotation(s) ready to compare" : 'Closed without any quotation', 'url' => $r->live_quotes_count ? route('rfqs.compare', $r) : route('rfqs.show', $r)]);
        }
        foreach ($pos()->where('status', 'draft')->with('supplier')->get() as $p) {
            $attention->push(['sev' => 'info', 'ref' => $p->number, 'title' => $p->supplier->name, 'why' => 'Draft purchase order waiting to be issued', 'url' => route('orders.show', $p)]);
        }
        foreach ($pos()->where('status', 'issued')->where('issued_at', '<=', now()->subDays(3))->with('supplier')->get() as $p) {
            $attention->push(['sev' => 'warn', 'ref' => $p->number, 'title' => $p->supplier->name, 'why' => 'Not acknowledged by the supplier for '.(int) $p->issued_at->diffInDays(now()).' days', 'url' => route('orders.show', $p)]);
        }

        $all = $rfqs()->withCount('liveQuotes')->get(['id', 'status']);
        $orders = $pos()->with('supplier:id,name')->get(['id', 'supplier_id', 'status', 'total_cents', 'currency', 'created_at']);
        $months = \App\Support\Chart::lastMonths();
        $cnt = fn (string $st) => $all->where('status', $st)->count();
        $c = \App\Support\Chart::COLORS;
        $live = $orders->where('status', '!=', 'cancelled');
        // Cents from different currencies must never be added or ranked as USD.
        $codes = $live->pluck('currency')->filter()->unique()->values();
        $currency = $codes->count() > 1 ? null : ($codes->first() ?? 'AZN');
        $valued = $currency === null ? collect() : $live;
        $top = $valued->groupBy('supplier_id')->map(fn ($g) => [$g->first()->supplier->name, (int) $g->sum('total_cents'), \App\Support\Money::format((int) $g->sum('total_cents'), $currency)])->sortByDesc(fn ($x) => $x[1])->take(5)->values()->all();
        $closing = $rfqs()->where('status', 'open')->whereBetween('deadline_at', [now(), now()->addDays(7)])->count();

        return [
            'closing' => $closing,
            'viz' => [
                'donut' => \App\Support\Chart::donut([['Open', $cnt('open'), $c[0]], ['Closed, comparing', $cnt('closed'), $c[3]], ['Awarded', $cnt('awarded'), $c[2]], ['Draft', $cnt('draft'), $c[5]], ['Cancelled', $cnt('cancelled'), $c[4]]], $all->count(), 'requests'),
                'total' => (int) $valued->sum('total_cents'), 'orders' => $live->count(),
                'currency' => $currency, 'mixed_currency' => $currency === null,
                'area' => $currency === null ? '' : \App\Support\Chart::area(array_values($months), array_map(fn ($k) => intdiv((int) $valued->filter(fn ($p) => $p->created_at->format('Y-m') === $k)->sum('total_cents'), 100), array_keys($months)), \App\Support\Money::SYMBOLS[$currency] ?? $currency),
                'funnel' => \App\Support\Chart::funnel([['Published', $all->where('status', '!=', 'draft')->count()], ['Received quotations', $all->where('live_quotes_count', '>', 0)->count()], ['Awarded', $live->count()], ['Delivered', $live->whereIn('status', ['delivered', 'invoiced'])->count()], ['Invoiced', $live->where('status', 'invoiced')->count()]]),
                'rank' => $currency === null ? '' : \App\Support\Chart::rank($top),
            ],
            'strip' => [
                ['l' => 'Open requests', 'v' => $rfqs()->where('status', 'open')->count(), 's' => $rfqs()->where('status', 'draft')->count().' in draft', 'href' => route('rfqs.index', ['status' => 'open'])],
                ['l' => 'Ready to compare', 'v' => $rfqs()->where('status', 'closed')->whereHas('liveQuotes')->count(), 's' => 'closed, with quotations', 'href' => route('compare.index')],
                ['l' => 'Orders to issue', 'v' => $pos()->where('status', 'draft')->count(), 's' => 'draft purchase orders', 'href' => route('orders.index', ['status' => 'draft'])],
                ['l' => 'Awaiting delivery', 'v' => $pos()->whereIn('status', ['issued', 'acknowledged', 'in_transit'])->count(), 's' => 'issued and not yet delivered', 'href' => route('orders.index')],
            ],
            'attention' => $attention->sortBy(fn ($a) => ['bad' => 0, 'warn' => 1, 'info' => 2][$a['sev']])->values()->take(8),
            'recent' => $rfqs()->with('category')->withCount('liveQuotes')->latest()->limit(6)->get(),
            'pending' => $u->allowed('suppliers', 'A') ? Supplier::where('status', 'pending')->count() : 0,
            'logs' => $u->isAdmin() ? ActivityLog::latest('created_at')->limit(8)->get() : ActivityLog::where('user_id', $u->id)->latest('created_at')->limit(8)->get(),
        ];
    }

    private function supplier(): array
    {
        $u = $this->me();
        $sid = $u->supplier_id;
        $open = Rfq::visibleTo($u)->where('status', 'open')->with(['invitations' => fn ($q) => $q->where('supplier_id', $sid), 'quotes' => fn ($q) => $q->where('supplier_id', $sid)])->orderBy('deadline_at')->get();
        $todo = $open->filter(fn (Rfq $r) => ! $r->quotes->firstWhere('status', 'submitted') && $r->invitations->first()?->status !== 'declined');

        $mine = Rfq::visibleTo($u)->where('status', '!=', 'draft')->with(['invitations' => fn ($q) => $q->where('supplier_id', $sid), 'quotes' => fn ($q) => $q->where('supplier_id', $sid)])->get();
        $state = fn (Rfq $r) => \App\Livewire\Quotes\Invitations::state($r);
        $by = $mine->groupBy($state)->map->count();
        $n = fn (array $keys) => (int) collect($keys)->sum(fn ($k) => $by[$k] ?? 0);
        $won = PurchaseOrder::visibleTo($u)->where('status', '!=', 'cancelled')->with('buyer:id,name')->get();
        $months = \App\Support\Chart::lastMonths();
        $c = \App\Support\Chart::COLORS;
        $codes = $won->pluck('currency')->filter()->unique()->values();
        $currency = $codes->count() > 1 ? null : ($codes->first() ?? 'AZN');
        $valued = $currency === null ? collect() : $won;

        return [
            'viz' => [
                'donut' => \App\Support\Chart::donut([['Awaiting my response', $n(['pending', 'accepted', 'public', 'withdrawn']), $c[0]], ['Quoted, open', $n(['sent']), $c[1]], ['Under review', $n(['review']), $c[3]], ['Won', $n(['won']), $c[2]], ['Not won or missed', $n(['lost', 'declined', 'missed', 'cancelled']), $c[5]]], $mine->count(), 'requests'),
                'total' => (int) $valued->sum('total_cents'), 'orders' => $won->count(),
                'currency' => $currency, 'mixed_currency' => $currency === null,
                'area' => $currency === null ? '' : \App\Support\Chart::area(array_values($months), array_map(fn ($k) => intdiv((int) $valued->filter(fn ($p) => $p->created_at->format('Y-m') === $k)->sum('total_cents'), 100), array_keys($months)), \App\Support\Money::SYMBOLS[$currency] ?? $currency),
                'funnel' => \App\Support\Chart::funnel([['Invited', $mine->count()], ['Quoted', $n(['sent', 'review', 'won', 'lost'])], ['Won', $won->count()], ['Delivered', $won->whereIn('status', ['delivered', 'invoiced'])->count()], ['Invoiced', $won->where('status', 'invoiced')->count()]]),
                'rank' => $currency === null ? '' : \App\Support\Chart::rank($valued->groupBy('buyer_id')->map(fn ($g) => [$g->first()->buyer->name, (int) $g->sum('total_cents'), \App\Support\Money::format((int) $g->sum('total_cents'), $currency)])->sortByDesc(fn ($x) => $x[1])->take(5)->values()->all()),
            ],
            'supplier' => $u->supplier,
            'strip' => [
                ['l' => 'Requests to answer', 'v' => $todo->count(), 's' => 'open and not yet quoted', 'href' => route('quotes.invitations')],
                ['l' => 'Quotations under review', 'v' => Quote::where('supplier_id', $sid)->where('status', 'submitted')->count(), 's' => 'submitted, no decision yet', 'href' => route('quotes.invitations', ['tab' => 'sent'])],
                ['l' => 'Orders needing action', 'v' => PurchaseOrder::visibleTo($u)->whereIn('status', ['issued', 'acknowledged', 'delivered'])->count(), 's' => 'acknowledge, dispatch or invoice', 'href' => route('orders.index')],
                ['l' => 'Requests won', 'v' => Quote::where('supplier_id', $sid)->where('status', 'won')->count(), 's' => 'all time', 'href' => route('orders.index')],
            ],
            'todo' => $todo->take(6),
            'orders' => PurchaseOrder::visibleTo($u)->with('rfq')->latest()->limit(5)->get(),
        ];
    }

    /** Sellers and market workers see their store; supplier field workers see their assessments. */
    private function other(): array
    {
        $u = $this->me();
        $store = in_array($u->role, ['seller', 'market_worker'], true) ? $u->store?->load('market') : null;
        if (! $store) {
            $fa = \App\Models\FieldAssessment::visibleTo($u)->latest('id')->limit(8)->get();

            return ['store' => null, 'assessments' => $fa, 'todo' => $fa->whereIn('status', ['assigned', 'in_progress', 'rejected'])->count()];
        }
        $orders = \App\Models\MarketOrder::where('store_id', $store->id);
        $tasks = \App\Models\WorkerTask::where('store_id', $store->id)->where('status', 'open')->count();
        $month = (int) (clone $orders)->where('status', '!=', 'cancelled')->where('created_at', '>=', now()->startOfMonth())->sum('total_cents');
        $sc = [[route('market.tasks'), 'check', 'Tasks', $tasks . ' to pick or deliver'], [route('market.orders.index'), 'truck', 'Sales', 'Every order placed with the store']];
        if ($u->role === 'seller') {
            array_unshift($sc, [route('market.offers'), 'box', 'My products', 'Prices, stock and price list import']);
            $sc[] = [route('market.shop'), 'store', 'Marketplace', 'See how your prices compare'];
        }

        return [
            'store' => $store, 'shortcuts' => $sc,
            'strip' => [
                ['l' => 'New orders', 'v' => (clone $orders)->where('status', 'placed')->count(), 's' => 'waiting to be confirmed', 'href' => route('market.orders.index', ['status' => 'placed'])],
                ['l' => 'Open tasks', 'v' => $tasks, 's' => 'pick or deliver', 'href' => route('market.tasks')],
                ['l' => 'Products on sale', 'v' => \App\Models\Offer::where('store_id', $store->id)->where('status', 'active')->where('stock_milli', '>', 0)->count(), 's' => 'with stock'],
                ['l' => 'Sales this month', 'v' => \App\Support\Money::format($month, 'USD', false), 's' => 'not counting cancelled orders'],
            ],
            'orders' => (clone $orders)->whereIn('status', ['placed', 'confirmed', 'ready'])->with('buyer', 'supplier')->oldest('id')->limit(8)->get(),
            'low' => \App\Models\Offer::where('store_id', $store->id)->where('status', 'active')->whereColumn('stock_milli', '<', 'moq_milli')->with('product')->limit(8)->get(),
        ];
    }
}
