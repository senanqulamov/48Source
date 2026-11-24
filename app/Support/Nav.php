<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\User;

/** The menu for a user: only areas their role may view. */
final class Nav
{
    /** One sentence per menu entry, used by the guided tour. */
    public const TIPS = [
        'dashboard' => 'Your start page: what needs you today, and charts of your requests and orders.',
        'rfqs.index' => 'Requests for quotation. Create one, invite suppliers and follow it to the award.',
        'compare.index' => 'Closed requests with quotations. Compare them side by side, run a best-and-final round or an auction, then award.',
        'quotes.invitations' => 'Requests you are invited to. Accept, send your quotation and revise it until the deadline.',
        'orders.index' => 'Purchase orders from awards. Follow each one from issue to delivery, record receipts and invoices.',
        'requisitions.index' => 'Internal requests to buy. They pass an approval chain before becoming a request for quotation.',
        'rfis.index' => 'Questions to suppliers before prices are asked.',
        'contracts.index' => 'Framework contracts with fixed prices. Place release orders against them.',
        'market.shop' => 'The marketplace. Search products, compare the sellers of each one and fill your basket.',
        'market.sourcing' => 'Pick a request and see, item by item, which stores sell it and at what price.',
        'market.orders.index' => 'Marketplace orders, one per store.',
        'market.offers' => 'Your price list: prices, stock and smallest order. Import it from Excel.',
        'market.tasks' => 'Pick and deliver the goods of confirmed orders.',
        'invoices.index' => 'Invoices checked against the order and what was received, and all goods receipts.',
        'accounting.index' => 'Export approved invoices as journal lines for your accounting system.',
        'reports.index' => 'Spend by month, supplier and category, budgets, savings and service figures.',
        'suppliers.index' => 'Every supplier with status, rating and a 360 page with documents, scorecards and risk.',
        'suppliers.profile' => 'Your company profile as the buyer sees it: documents, scorecards and actions.',
        'assessments.index' => 'Site checks asked by the buyer and carried out by the supplier.',
        'categories.index' => 'Categories decide which suppliers are suggested for a request.',
        'markets.index' => 'Markets, stores and which accounts belong to each store.',
        'messages.index' => 'Conversations between the buyer and a supplier.',
        'users.index' => 'Accounts, roles and what each role is allowed to do.',
        'logs.index' => 'Who did what and when.',
        'outbox.index' => 'Every email the system wrote, and whether it went out.',
        'notifications.index' => 'Everything the system told you.',
        'assistant.index' => 'Rule-based helpers: draft a request from a sentence, read quotations, suggest suppliers.',
        'workers.index' => 'The workers of your store or company.',
        'monitoring.index' => 'Active requests against their deadlines, and everything that is overdue.',
        'spend.index' => 'Committed spend by month, supplier and category, with a yearly budget per category.',
        'savings.index' => 'What each award saved against the budget and against the average quotation.',
        'kpi.index' => 'Service figures: response rate, on-time delivery, invoices matching first time.',
        'sla.index' => 'Reminders the system sends and the rules behind them.',
        'sup360.index' => 'Every supplier with risk, documents, scorecards and open actions in one list.',
        'health.index' => 'Whether the database, cache, mail, scheduler and file storage are working.',
        'flags.index' => 'Switch optional features on or off for the whole organisation.',
        'impex.index' => 'Import suppliers from Excel or CSV and see where each list can be exported.',
        'market.products' => 'Every product the stores list, with its sellers, lowest price and stock.',
        'market.places' => 'The markets, what is sold in each, and how many stores and workers they have.',
        'market.basket' => 'Products you picked in the marketplace. One order is placed per store.',
        'system.index' => 'Service status, feature switches, currencies and supplier import.',
    ];

    /**
     * Menu definition per role, in the order of the accepted prototype:
     * [group, [[route, label, icon, module (null = always)]]].
     */
    private static function groups(User $user, string $side): array
    {
        $sourcing = [
            ['rfqs.index', $user->isAdmin() ? 'RFQs' : 'My RFQs', 'file', 'rfqs'],
            ['compare.index', 'Compare quotations', 'cols', 'quotes'],
            ['orders.index', 'Purchase orders', 'box', 'orders'],
            ['requisitions.index', 'Requisitions', 'inbox', 'requisitions'],
            ['rfis.index', 'Information requests', 'info', 'rfqs'],
            ['contracts.index', 'Contracts', 'clip', 'contracts'],
        ];
        $finance = [
            ['invoices.index', 'Invoices & receipts', 'file', 'invoices'],
            ['spend.index', 'Spend & budgets', 'chart', 'spend'],
            ['savings.index', 'Savings', 'award', 'spend'],
        ];
        $insight = [
            ['sup360.index', 'Supplier 360', 'shield', 'suppliers'],
            ['assistant.index', 'Assistant', 'star', 'rfqs'],
        ];

        return match (true) {
            $user->isAdmin() => [
                ['Overview', [['dashboard', 'Dashboard', 'dash', null]]],
                ['Sourcing', $sourcing],
                ['Finance', $finance],
                ['Insight', $insight],
                ['Marketplace', [
                    ['market.products', 'Products', 'box', 'marketplace'],
                    ['categories.index', 'Categories', 'flag', 'catalog'],
                    ['market.places', 'Markets', 'store', 'catalog'],
                    ['market.orders.index', 'Marketplace orders', 'truck', 'marketplace'],
                ]],
                ['People', [
                    ['suppliers.index', 'Suppliers', 'building', 'suppliers'],
                    ['users.index', 'Users & roles', 'users', 'users'],
                    ['messages.index', 'Messages', 'mail', null],
                ]],
                ['Monitoring', [
                    ['monitoring.index', 'RFQ monitoring', 'pulse', 'reports'],
                    ['sla.index', 'SLA tracker', 'clock', 'reports'],
                    ['kpi.index', 'KPI reports', 'chart', 'reports'],
                ]],
                ['System', [
                    ['notifications.index', 'Notifications', 'bell', null],
                    ['logs.index', 'Logs', 'clip', 'system'],
                    ['health.index', 'System health', 'server', 'system'],
                    ['flags.index', 'Feature flags', 'flag', 'system'],
                    ['impex.index', 'Import / export', 'download', 'system'],
                    ['outbox.index', 'Email outbox', 'mail', 'system'],
                    ['accounting.index', 'Accounting export', 'download', 'invoices'],
                ]],
            ],
            $user->isSourcing() => [
                ['Workspace', [['dashboard', 'My dashboard', 'dash', null]]],
                ['Sourcing', $sourcing],
                ['Finance', $finance],
                ['Insight', $insight],
                ['Network', [
                    ['suppliers.index', 'Suppliers', 'building', 'suppliers'],
                    ['market.products', 'Products', 'box', 'marketplace'],
                    ['market.places', 'Markets', 'store', 'marketplace'],
                    ['messages.index', 'Messages', 'mail', null],
                ]],
                ['Marketplace', [
                    ['market.shop', 'Marketplace', 'store', 'marketplace'],
                    ['market.basket', 'Basket', 'box', 'marketplace'],
                    ['market.orders.index', 'My marketplace orders', 'truck', 'marketplace'],
                ]],
                ['Records', [['logs.index', 'Activity logs', 'clip', null]]],
            ],
            $user->role === 'supplier' => [
                ['Workspace', [['dashboard', 'Dashboard', 'dash', null]]],
                ['Sourcing', [
                    ['quotes.invitations', 'Invitations & quotes', 'inbox', 'quotes'],
                    ['orders.index', 'Purchase orders', 'box', 'orders'],
                ]],
                ['Marketplace', [
                    ['market.products', 'Products', 'box', 'marketplace'],
                    ['market.places', 'Markets', 'store', 'marketplace'],
                    ['market.orders.index', 'Marketplace orders', 'truck', 'marketplace'],
                    ['market.shop', 'Buy products', 'cols', 'marketplace'],
                    ['market.basket', 'Basket', 'box', 'marketplace'],
                ]],
                ['Team', [
                    ['workers.index', 'Field workers', 'users', null],
                    ['messages.index', 'Messages', 'mail', null],
                ]],
                ['Records', [['logs.index', 'Activity logs', 'clip', null]]],
                ['Company', [
                    ['suppliers.profile', 'Company profile', 'building', null],
                    ['contracts.index', 'Contracts', 'clip', 'contracts'],
                    ['invoices.index', 'Invoices', 'file', 'invoices'],
                    ['rfis.index', 'Information requests', 'info', 'rfqs'],
                ]],
            ],
            $user->role === 'seller' => [
                ['Workspace', [['dashboard', 'Dashboard', 'dash', null]]],
                ['Marketplace', [
                    ['market.offers', 'Products', 'box', 'catalog'],
                    ['market.places', 'Markets', 'store', 'marketplace'],
                    ['market.orders.index', 'Orders', 'truck', 'marketplace'],
                ]],
                ['Team', [
                    ['workers.index', 'Workers', 'users', null],
                    ['market.tasks', 'Worker tasks', 'check', 'marketplace'],
                ]],
                ['Records', [['logs.index', 'Activity logs', 'clip', null]]],
            ],
            $user->role === 'market_worker' => [
                ['My work', [
                    ['market.tasks', 'Today', 'clip', 'marketplace'],
                    ['market.orders.index', 'Orders', 'truck', 'marketplace'],
                ]],
            ],
            default => [
                ['My work', [
                    ['dashboard', 'Dashboard', 'dash', null],
                    ['assessments.index', 'Assigned RFQs', 'clip', 'field'],
                    ['logs.index', 'My activity', 'clock', null],
                ]],
            ],
        };
    }

    private static function side(User $user): string
    {
        return $user->isSourcing() ? 'buy' : ($user->role === 'supplier' ? 'sell' : 'other');
    }

    /** @return array<int, array{0:string,1:array<int,array{route:string,label:string,icon:string,count:int}>}> */
    public static function for(User $user): array
    {
        $side = self::side($user);
        $out = [];
        foreach (self::groups($user, $side) as [$label, $items]) {
            $keep = [];
            foreach ($items as [$route, $text, $icon, $module]) {
                if (($module !== null && ! $user->allowed($module)) || ($route === 'suppliers.profile' && ! $user->supplier_id) || ($route === 'workers.index' && ! $user->supplier_id && ! $user->store_id) || (in_array($route, ['market.shop', 'market.basket'], true) && ! $user->canShop())) {
                    continue;
                }
                if ($route === 'suppliers.profile' && ! $user->supplier_id) {
                    continue;
                }
                $keep[] = ['route' => $route, 'label' => __($text), 'tip' => self::TIPS[$route] ?? '', 'icon' => $icon, 'count' => self::count($route, $user), 'url' => $route === 'suppliers.profile' ? route('suppliers.show', $user->supplier_id) : route($route)];
            }
            if ($keep) {
                $out[] = [__($label), $keep];
            }
        }

        return $out;
    }

    /** Name of the menu group a route belongs to, shown in the page trail. */
    public static function groupOf(?User $user, string $routeName): ?string
    {
        if (! $user || $routeName === 'dashboard') {
            return null;
        }
        // An exact menu entry wins; otherwise the group of the area the page belongs to.
        foreach (self::groups($user, self::side($user)) as [$label, $items]) {
            foreach ($items as [$route]) {
                if ($route === $routeName) {
                    return __($label);
                }
            }
        }
        $area = self::area($routeName);
        foreach (self::groups($user, self::side($user)) as [$label, $items]) {
            foreach ($items as [$route]) {
                if (self::area($route) === $area) {
                    return __($label);
                }
            }
        }

        return null;
    }

    /** First part of a route name, with the pages that share a menu entry folded together. */
    public static function area(string $routeName): string
    {
        $a = explode('.', $routeName)[0];

        return ['compare' => 'compare'][$a] ?? $a;
    }

    private static function count(string $route, User $user): int
    {
        return match ($route) {
            'rfqs.index' => Rfq::visibleTo($user)->where('status', 'open')->count(),
            'compare.index' => Rfq::visibleTo($user)->where('status', 'closed')->whereHas('liveQuotes')->count(),
            'quotes.invitations' => $user->supplier_id ? Rfq::visibleTo($user)->where('status', 'open')
                ->whereHas('invitations', fn ($q) => $q->where('supplier_id', $user->supplier_id)->where('status', 'pending'))->count() : 0,
            'orders.index' => $user->role === 'supplier'
                ? PurchaseOrder::visibleTo($user)->whereIn('status', ['issued', 'acknowledged', 'delivered'])->count()
                : PurchaseOrder::visibleTo($user)->whereIn('status', ['draft', 'in_transit'])->count(),
            'notifications.index' => $user->unreadNotifications()->count(),
            'requisitions.index' => $user->allowed('requisitions', 'A') ? \App\Models\Requisition::visibleTo($user)->where('status', 'pending')->where('requester_id', '!=', $user->id)->count() : 0,
            'invoices.index' => $user->isSourcing() ? \App\Models\Invoice::visibleTo($user)->whereIn('status', ['matched', 'disputed'])->count() : 0,
            'assessments.index' => \App\Models\FieldAssessment::visibleTo($user)->whereIn('status', $user->isSourcing() ? ['submitted'] : ['assigned', 'in_progress', 'rejected'])->count(),
            'rfis.index' => $user->role === 'supplier' ? \App\Models\Rfi::visibleTo($user)->where('status', 'open')->whereHas('responses', fn ($r) => $r->where('supplier_id', $user->supplier_id)->where('status', 'invited'))->count() : 0,
            'market.orders.index' => in_array($user->role, ['seller', 'market_worker'], true) ? \App\Models\MarketOrder::visibleTo($user)->where('status', 'placed')->count() : 0,
            'market.tasks' => $user->store_id ? \App\Models\WorkerTask::where('store_id', $user->store_id)->where('status', 'open')->count() : 0,
            'market.basket' => $user->canShop() ? \App\Models\CartItem::where('user_id', $user->id)->count() : 0,
            'messages.index' => app(\App\Services\MessageService::class)->unread($user),
            default => 0,
        };
    }
}
