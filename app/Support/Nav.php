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
        'system.index' => 'Service status, feature switches, currencies and supplier import.',
    ];

    /** Menu definition: [group, [[route, label, icon, module (null = always), side (null = both)]]]. */
    private static function groups(User $user, string $side): array
    {
        return [
            [$side === 'buy' ? 'Overview' : 'Workspace', [['dashboard', $side === 'buy' && ! $user->isAdmin() ? 'My dashboard' : 'Dashboard', 'dash', null, null]]],
            ['Sourcing', [
                ['rfqs.index', $user->isAdmin() ? 'RFQs' : 'My RFQs', 'file', 'rfqs', 'buy'],
                ['compare.index', 'Compare quotations', 'cols', 'quotes', 'buy'],
                ['quotes.invitations', 'Invitations & quotes', 'inbox', 'quotes', 'sell'],
                ['orders.index', 'Purchase orders', 'award', 'orders', null],
                ['requisitions.index', 'Requisitions', 'inbox', 'requisitions', 'buy'],
                ['rfis.index', 'Information requests', 'info', 'rfqs', $side === 'other' ? 'none' : null],
                ['contracts.index', 'Contracts', 'clip', 'contracts', null],
                ['assistant.index', 'Assistant', 'star', 'rfqs', 'buy'],
            ]],
            ['Marketplace', [
                ['market.shop', 'Marketplace', 'store', 'marketplace', $user->canShop() ? null : 'none'],
                ['market.sourcing', 'Source a request', 'cols', 'marketplace', $user->canShop() ? null : 'none'],
                ['market.orders.index', in_array($user->role, ['seller', 'market_worker'], true) ? 'Sales' : 'Marketplace orders', 'truck', 'marketplace', null],
                ['market.offers', 'My products', 'box', 'catalog', $user->role === 'seller' ? null : 'none'],
                ['market.tasks', 'Tasks', 'check', 'marketplace', in_array($user->role, ['seller', 'market_worker'], true) ? null : 'none'],
                ['workers.index', $user->role === 'seller' ? 'Workers' : 'Field workers', 'users', null, ($user->role === 'seller' && $user->store_id) || ($user->role === 'supplier' && $user->supplier_id) ? null : 'none'],
            ]],
            ['Finance', [
                ['invoices.index', 'Invoices & receipts', 'file', 'invoices', null],
                ['accounting.index', 'Accounting export', 'download', 'invoices', $user->isSourcing() && $user->allowed('invoices', 'A') ? null : 'none'],
                ['reports.index', 'Spend & reports', 'chart', 'spend', 'buy'],
                ['monitoring.index', 'Monitoring & SLA', 'pulse', 'reports', 'buy'],
            ]],
            ['Network', [
                ['suppliers.index', 'Suppliers', 'building', 'suppliers', 'buy'],
                ['suppliers.profile', 'Company profile', 'building', null, 'sell'],
                ['assessments.index', 'Field assessments', 'camera', 'field', null],
                ['categories.index', 'Categories', 'flag', 'catalog', 'buy'],
                ['markets.index', 'Markets & stores', 'pin', 'catalog', $user->allowed('catalog', 'A') ? null : 'none'],
                ['messages.index', 'Messages', 'mail', null, $side === 'other' ? 'none' : null],
            ]],
            ['Administration', [
                ['users.index', 'Users & roles', 'users', 'users', null],
                ['logs.index', 'Activity log', 'clock', 'system', null],
                ['outbox.index', 'Email outbox', 'mail', 'system', null],
                ['system.index', 'System', 'server', 'system', null],
            ]],
            ['Records', [['notifications.index', 'Notifications', 'bell', null, null]]],
        ];
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
            foreach ($items as [$route, $text, $icon, $module, $for]) {
                if (($for !== null && $for !== $side) || ($module !== null && ! $user->allowed($module))) {
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
            'market.shop' => $user->canShop() ? \App\Models\CartItem::where('user_id', $user->id)->count() : 0,
            'messages.index' => app(\App\Services\MessageService::class)->unread($user),
            default => 0,
        };
    }
}
