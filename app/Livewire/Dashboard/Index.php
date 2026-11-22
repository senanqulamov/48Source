<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Traits\WithLogging;
use App\Models\Quote;
use App\Models\Request;
use App\Models\SupplierInvitation;
use App\Models\User;
use App\Models\WorkflowEvent;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    use WithLogging;

    /** @var array<string, mixed> */
    public array $approved = [];

    public function mount(): void
    {
        $this->logPageView('Dashboard');
        $this->loadApprovedDashboard();
    }

    /**
     * Query only what the approved overview renders. Do not hydrate all historical
     * orders, products, logs and dashboards on each navigation.
     */
    protected function loadApprovedDashboard(): void
    {
        $now = now();
        $open = Request::query()->where('status', 'open')->count();
        $pending = Quote::query()->where('status', 'pending')->count();
        $closedAwaitingAward = Request::query()->where('status', 'closed')
            ->where('updated_at', '<=', $now->copy()->subDays(3))->count();
        $soon = Request::query()->where('status', 'open')
            ->whereBetween('deadline', [$now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()])
            ->count();
        $activeOrders = \App\Models\ProcurementPurchaseOrder::query()
            ->whereIn('status', ['issued', 'acknowledged', 'in_transit', 'delivered'])->count();
        $users = User::query()->count();
        $readyToCompare = Request::query()->where('status', 'closed')
            ->whereHas('quotes')->count();

        $attention = Request::query()->with('buyer:id,name')->withCount('quotes')
            ->where(function ($query) use ($now) {
                $query->where(function ($openQuery) use ($now) {
                    $openQuery->where('status', 'open')
                        ->where('deadline', '<=', $now->copy()->addDays(7)->endOfDay());
                })->orWhere(function ($closedQuery) use ($now) {
                    $closedQuery->where('status', 'closed')
                        ->where('updated_at', '<=', $now->copy()->subDays(3));
                });
            })
            ->orderBy('deadline')->limit(6)->get()
            ->map(function (Request $request) use ($now) {
                $needsAward = $request->status === 'closed';
                $due = $needsAward ? $request->updated_at->copy()->addDays(5) : $request->deadline;
                return [
                    'id' => $request->id,
                    'title' => $request->title,
                    'reason' => $needsAward ? 'Closed without an award decision' : 'Quotation deadline approaching',
                    'due' => $due->format('d M Y'),
                    'relative' => $due->diffForHumans($now),
                    'urgency' => $due->lt($now) ? 'bad' : 'warn',
                    'action' => $needsAward ? 'Compare' : 'View',
                ];
            })->all();

        $deadlines = Request::query()->with('buyer:id,name')
            ->withCount(['quotes', 'supplierInvitations'])
            ->where('status', 'open')->where('deadline', '>=', $now->copy()->startOfDay())
            ->orderBy('deadline')->limit(5)->get()
            ->map(fn (Request $request) => [
                'id' => $request->id,
                'title' => $request->title,
                'buyer' => data_get($request, 'buyer.name', '—'),
                'deadline' => $request->deadline->format('d M Y'),
                'quotes' => $request->quotes_count,
                'invited' => $request->supplier_invitations_count,
            ])->all();

        // One 8-week window, populated from real request and quote creation timestamps.
        $start = $now->copy()->startOfWeek()->subWeeks(7);
        // Aggregate at the database level: at most 56 daily records per model,
        // rather than hydrating every RFQ and quotation into each dashboard visit.
        $published = Request::query()->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) AS activity_day, COUNT(*) AS activity_count')
            ->groupByRaw('DATE(created_at)')->get()
            ->mapWithKeys(fn ($row) => [(string) $row->getAttribute('activity_day') => (int) $row->getAttribute('activity_count')]);
        $received = Quote::query()->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) AS activity_day, COUNT(*) AS activity_count')
            ->groupByRaw('DATE(created_at)')->get()
            ->mapWithKeys(fn ($row) => [(string) $row->activity_day => (int) $row->activity_count]);
        $weeks = [];
        for ($index = 0; $index < 8; $index++) {
            $week = $start->copy()->addWeeks($index);
            $next = $week->copy()->addWeek();
            $publishedCount = 0;
            $receivedCount = 0;
            for ($day = $week->copy(); $day->lt($next); $day->addDay()) {
                $key = $day->toDateString();
                $publishedCount += $published->get($key, 0);
                $receivedCount += $received->get($key, 0);
            }
            $weeks[] = [
                'label' => $week->format('j M'),
                'published' => $publishedCount,
                'quoted' => $receivedCount,
            ];
        }
        $maxChart = max(1, ...array_merge(
            array_column($weeks, 'published'), array_column($weeks, 'quoted')
        ));

        $invitedCount = SupplierInvitation::query()->count();
        $respondedCount = SupplierInvitation::query()
            ->whereIn('status', ['accepted', 'quoted', 'responded'])->count();

        $events = WorkflowEvent::query()->with('user:id,name')->latest('occurred_at')
            ->limit(5)->get()->map(fn (WorkflowEvent $event) => [
                'description' => $event->description ?: str_replace('_', ' ', $event->event_type),
                'by' => data_get($event, 'user.name', 'System'),
                'when' => $event->occurred_at->diffForHumans(),
            ])->all();

        $this->approved = [
            'open' => $open,
            'attentionCount' => $closedAwaitingAward + $soon,
            'pending' => $pending,
            'orders' => $activeOrders,
            'users' => $users,
            'compare' => $readyToCompare,
            'attention' => $attention,
            'deadlines' => $deadlines,
            'weeks' => $weeks,
            'chartMax' => $maxChart,
            'participation' => $invitedCount > 0 ? round($respondedCount / $invitedCount * 100) : 0,
            'participationCounts' => $respondedCount.' / '.$invitedCount,
            'events' => $events,
        ];
    }


    public function render(): View
    {
        return view('livewire.dashboard.index');
    }
}
