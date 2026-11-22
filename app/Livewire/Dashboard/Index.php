<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Traits\WithLogging;
use App\Models\Log;
use App\Models\Market;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Request;
use App\Models\SupplierInvitation;
use App\Models\User;
use App\Models\WorkflowEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    use WithLogging;
    public $stats = [];

    public array $approved = [];

    public $recentActivity = [];

    public $ordersByStatus = [];

    public $salesByDay = [];

    public $topProducts = [];

    public $recentOrders = [];

    public $userActivity = [];

    public $systemHealth = [];

    public $rfqStats = [];

    public $rfqsByStatus = [];

    public $quotesByStatus = [];

    public $recentRfqs = [];

    public $recentQuotes = [];

    public $workflowActivity = [];

    // Livewire listeners for frontend events (chart clicks and quick actions)
    protected $listeners = [
        'chartSegmentClicked',
        'inviteSupplier',
        'createRfq',
        'exportDashboardCsv',
    ];

    public function mount()
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
        $publishedDates = Request::query()->where('created_at', '>=', $start)
            ->pluck('created_at')->map(fn ($date) => \Carbon\Carbon::parse($date));
        $receivedDates = Quote::query()->where('created_at', '>=', $start)
            ->pluck('created_at')->map(fn ($date) => \Carbon\Carbon::parse($date));
        $weeks = [];
        for ($index = 0; $index < 8; $index++) {
            $week = $start->copy()->addWeeks($index);
            $next = $week->copy()->addWeek();
            $weeks[] = [
                'label' => $week->format('j M'),
                'published' => $publishedDates->filter(fn ($date) => $date >= $week && $date < $next)->count(),
                'quoted' => $receivedDates->filter(fn ($date) => $date >= $week && $date < $next)->count(),
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

    /**
     * Handle chart segment clicks from the frontend.
     * Logs the selection and dispatches a browser event that can be used to apply filters.
     */
    public function chartSegmentClicked($chartId, $label = null, $index = null)
    {
        $this->logAction('ui', "Chart segment clicked: {$chartId} - {$label}", action: 'chart.click', metadata: ['chart' => $chartId, 'label' => $label, 'index' => $index]);
        // Notify browser (so JS can respond if desired)
        $this->dispatchBrowserEvent('dpanel:chartSegmentSelected', ['chartId' => $chartId, 'label' => $label, 'index' => $index]);
    }

    public function inviteSupplier()
    {
        $this->logAction('ui', 'Invite Supplier clicked', action: 'invite.supplier');
        $this->dispatchBrowserEvent('dpanel:open-invite-supplier');
    }

    public function createRfq()
    {
        $this->logAction('ui', 'Create RFQ clicked', action: 'rfq.create');
        $this->dispatchBrowserEvent('dpanel:open-create-rfq');
    }

    public function exportDashboardCsv()
    {
        $this->logAction('ui', 'Export dashboard CSV requested', action: 'export.dashboard');
        $this->dispatchBrowserEvent('dpanel:export-started');
    }

    protected function loadStats()
    {
        // Get current counts
        $totalUsers = User::count();
        $totalOrders = Order::count();
        $totalProducts = Product::count();
        $totalMarkets = Market::count();

        // Get previous period counts for comparison
        $previousUsers = User::where('created_at', '<', now()->subDays(30))->count();
        $previousOrders = Order::where('created_at', '<', now()->subDays(30))->count();
        $previousProducts = Product::where('created_at', '<', now()->subDays(30))->count();

        // Calculate percentage changes
        $usersChange = $previousUsers > 0 ? (($totalUsers - $previousUsers) / $previousUsers) * 100 : 0;
        $ordersChange = $previousOrders > 0 ? (($totalOrders - $previousOrders) / $previousOrders) * 100 : 0;
        $productsChange = $previousProducts > 0 ? (($totalProducts - $previousProducts) / $previousProducts) * 100 : 0;

        // Calculate total revenue
        $totalRevenue = Order::where('status', 'completed')->sum('total');
        $previousRevenue = Order::where('status', 'completed')
            ->where('created_at', '<', now()->subDays(30))
            ->sum('total');
        $revenueChange = $previousRevenue > 0 ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        $this->stats = [
            'users' => [
                'count' => $totalUsers,
                'change' => round($usersChange, 1),
                'label' => __('Total Users'),
                'icon' => 'users',
                'color' => 'blue',
            ],
            'orders' => [
                'count' => $totalOrders,
                'change' => round($ordersChange, 1),
                'label' => __('Total Orders'),
                'icon' => 'shopping-bag',
                'color' => 'green',
            ],
            'products' => [
                'count' => $totalProducts,
                'change' => round($productsChange, 1),
                'label' => __('Total Products'),
                'icon' => 'cube',
                'color' => 'purple',
            ],
            'revenue' => [
                'count' => '$'.number_format($totalRevenue, 2),
                'change' => round($revenueChange, 1),
                'label' => __('Total Revenue'),
                'icon' => 'currency-dollar',
                'color' => 'yellow',
            ],
            'markets' => [
                'count' => $totalMarkets,
                'change' => 0,
                'label' => __('Total Markets'),
                'icon' => 'building-storefront',
                'color' => 'red',
            ],
        ];
    }

    protected function loadRfqStats()
    {
        // Get RFQ counts
        $totalRfqs = Request::count();
        $openRfqs = Request::where('status', 'open')->count();
        $draftRfqs = Request::where('status', 'draft')->count();
        $closedRfqs = Request::where('status', 'closed')->count();
        $awardedRfqs = Request::where('status', 'awarded')->count();

        // Get Quote counts
        $totalQuotes = Quote::count();
        $pendingQuotes = Quote::where('status', 'pending')->count();
        $acceptedQuotes = Quote::where('status', 'accepted')->count();

        // Get Supplier counts
        $totalSuppliers = User::whereHas('roles', function($q) {
            $q->where('name', 'supplier');
        })->count();
        $activeSuppliers = SupplierInvitation::where('status', 'accepted')
            ->distinct('supplier_id')
            ->count('supplier_id');

        // Get Workflow Events
        $totalWorkflowEvents = WorkflowEvent::count();
        $eventsToday = WorkflowEvent::whereDate('occurred_at', today())->count();

        // Calculate previous period
        $previousRfqs = Request::where('created_at', '<', now()->subDays(30))->count();
        $previousQuotes = Quote::where('created_at', '<', now()->subDays(30))->count();

        $rfqsChange = $previousRfqs > 0 ? (($totalRfqs - $previousRfqs) / $previousRfqs) * 100 : 0;
        $quotesChange = $previousQuotes > 0 ? (($totalQuotes - $previousQuotes) / $previousQuotes) * 100 : 0;

        // Additional counts for new dashboard cards
        // Pending approvals: try common RFQ statuses, fallback to approval-like workflow events
        $pendingApprovals = Request::whereIn('status', ['pending_approval', 'awaiting_approval', 'approval_pending'])->count();
        if ($pendingApprovals <= 0) {
            $pendingApprovals = WorkflowEvent::where('event_type', 'like', '%approval%')->count();
        }

        // Purchase orders: total orders
        $purchaseOrders = Order::count();

        // SLA reminders: workflow events mentioning SLA (fallback to 0 if none)
        $slaReminders = WorkflowEvent::where('event_type', 'like', '%sla%')
            ->orWhere('event_type', 'like', '%reminder%')
            ->count();

        $this->rfqStats = [
            'totalRfqs' => $totalRfqs,
            'openRfqs' => $openRfqs,
            'draftRfqs' => $draftRfqs,
            'closedRfqs' => $closedRfqs,
            'awardedRfqs' => $awardedRfqs,
            'totalQuotes' => $totalQuotes,
            'pendingQuotes' => $pendingQuotes,
            'acceptedQuotes' => $acceptedQuotes,
            'totalSuppliers' => $totalSuppliers,
            'activeSuppliers' => $activeSuppliers,
            'totalWorkflowEvents' => $totalWorkflowEvents,
            'eventsToday' => $eventsToday,
            'rfqsChange' => round($rfqsChange, 1),
            'quotesChange' => round($quotesChange, 1),
            'pendingApprovals' => $pendingApprovals,
            'purchaseOrders' => $purchaseOrders,
            'slaReminders' => $slaReminders,
        ];

        // RFQs by status for chart
        $this->rfqsByStatus = Request::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'label' => ucfirst($item->status),
                'value' => $item->count,
            ])
            ->toArray();

        // Quotes by status for chart
        $this->quotesByStatus = Quote::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'label' => ucfirst($item->status),
                'value' => $item->count,
            ])
            ->toArray();

        // Recent RFQs
        $this->recentRfqs = Request::with(['buyer', 'items'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($rfq) {
                return [
                    'id' => $rfq->id,
                    'title' => $rfq->title,
                    'buyer' => $rfq->buyer?->name ?? 'Unknown',
                    'status' => $rfq->status,
                    'items_count' => $rfq->items->count(),
                    'deadline' => $rfq->deadline?->format('M d, Y'),
                    'created_at' => $rfq->created_at->diffForHumans(),
                ];
            })
            ->toArray();

        // Recent Quotes
        $this->recentQuotes = Quote::with(['supplier', 'request'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($quote) {
                return [
                    'id' => $quote->id,
                    'request_title' => $quote->request?->title ?? 'N/A',
                    'supplier' => $quote->supplier?->name ?? 'Unknown',
                    'status' => $quote->status,
                    'total_price' => $quote->total_price,
                    'submitted_at' => $quote->submitted_at?->diffForHumans() ?? $quote->created_at->diffForHumans(),
                ];
            })
            ->toArray();

        // Recent Workflow Events
        $this->workflowActivity = WorkflowEvent::with(['user', 'eventable'])
            ->latest('occurred_at')
            ->limit(10)
            ->get()
            ->map(function ($event) {
                return [
                    'user' => $event->user?->name ?? 'System',
                    'event_type' => $event->event_type,
                    'description' => $event->description,
                    'occurred_at' => $event->occurred_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    protected function loadChartData()
    {
        // Orders by status
        $this->ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'label' => ucfirst($item->status),
                'value' => $item->count,
            ])
            ->toArray();

        // Sales by day (last 30 days)
        $this->salesByDay = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
            'date' => $item->date,
            'total' => (float) $item->total,
            'count' => $item->count,
        ])
            ->toArray();

        // Top products using order_items
        $this->topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('COUNT(DISTINCT order_items.order_id) as orders'), DB::raw('SUM(order_items.subtotal) as revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('orders')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->name,
                'orders' => (int) $item->orders,
                'revenue' => (float) $item->revenue,
            ])
            ->toArray();

        // User activity over time (last 30 days)
        $this->userActivity = Log::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('type', 'page_view')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
            'date' => $item->date,
            'count' => $item->count,
        ])
            ->toArray();

//        dd($this->userActivity);
    }

    protected function loadRecentActivity()
    {
        // Recent orders (show first product or items count)
        $this->recentOrders = Order::with(['user', 'items.product'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                $first = $order->items->first();
                $productLabel = $first?->product?->name ?: 'Unknown';
                if ($order->items->count() > 1) {
                    $productLabel .= ' +'.($order->items->count() - 1).' more';
                }
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'user' => $order->user?->name ?? 'Unknown',
                    'product' => $productLabel,
                    'total' => $order->total,
                    'status' => $order->status,
                    'created_at' => $order->created_at->diffForHumans(),
                ];
            })
            ->toArray();

        // Recent activity logs
        $this->recentActivity = Log::with('user')
            ->whereIn('type', ['create', 'update', 'delete'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'user' => $log->user?->name ?? 'System',
                'message' => $log->message,
                'type' => $log->type,
                'created_at' => $log->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    protected function loadSystemHealth()
    {
        // Get log statistics
        $totalLogs = Log::count();
        $logsToday = Log::whereDate('created_at', today())->count();
        $errorLogsToday = Log::whereDate('created_at', today())->where('type', 'error')->count();

        // Get active users today
        $activeUsersToday = Log::whereDate('created_at', today())
            ->distinct('user_id')
            ->count('user_id');

        // Calculate system health score
        $healthScore = 100;
        if ($errorLogsToday > 10) {
            $healthScore -= 20;
        }
        if ($activeUsersToday == 0) {
            $healthScore -= 10;
        }

        $this->systemHealth = [
            'score' => $healthScore,
            'totalLogs' => $totalLogs,
            'logsToday' => $logsToday,
            'errorLogsToday' => $errorLogsToday,
            'activeUsersToday' => $activeUsersToday,
            'status' => $healthScore >= 90 ? 'excellent' : ($healthScore >= 70 ? 'good' : 'warning'),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
