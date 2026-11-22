@props(['role' => 'admin'])

@php
    // Current routes, never prototype hash routes. Permissions are enforced by Laravel as well.
    $groups = match ($role) {
        'buyer' => [
            'Workspace' => [['Dashboard','buyer.dashboard','view_dashboard'],['My RFQs','buyer.rfq.index','view_rfqs'],['Compare quotations','procurement.compare.index','view_rfqs'],['Purchase orders','procurement.orders.index',null]],
            'Sourcing' => [['Products','buyer.products.index','view_products'],['Markets','buyer.markets.index','view_markets'],['Import / Export','buyer.import-export','view_dashboard']],
        ],
        'seller' => [
            'Workspace' => [['Dashboard','seller.dashboard','view_dashboard']],
            'Catalog' => [['Products','seller.products.index','view_products'],['Markets','seller.markets.index','view_markets']],
            'Operations' => [['Orders','seller.orders.index','view_orders'],['Workers','seller.workers.index','view_dashboard']],
        ],
        'supplier' => [
            'Workspace' => [['Dashboard','supplier.dashboard','view_dashboard']],
            'Sourcing' => [['Invitations','supplier.invitations.index','manage_supplier_invitations'],['Quotations','supplier.quotes.index','view_quotes'],['RFQs','supplier.rfq.index','view_rfqs']],
            'Operations' => [['Orders','supplier.orders.index','view_orders'],['Procurement POs','procurement.orders.index',null],['Field workers','supplier.workers.index','view_dashboard'],['Messages','supplier.messages.index',null]],
        ],
        'supplier_worker' => [
            'Workspace' => [['Dashboard','supplier.field.dashboard',null],['Assigned RFQs','supplier.field.rfq.index',null],['Messages','supplier.field.messages.index',null]],
        ],
        'market_worker' => ['My account' => [['Profile','user.profile',null]]],
        default => [
            'Workspace' => [['Dashboard','dashboard','view_dashboard']],
            'Sourcing' => [['RFQs','rfq.index','view_rfqs'],['Compare quotations','procurement.compare.index','view_rfqs'],['Purchase orders','procurement.orders.index',null]],
            'Catalog' => [['Products','products.index','view_products'],['Categories','categories.index','view_categories'],['Markets','markets.index','view_markets']],
            'People' => [['Suppliers','users.index','view_users'],['Users & roles','privacy.index','manage_roles']],
            'Monitoring' => [['RFQ monitoring & SLA','monitoring.rfq.index','view_monitoring'],['KPI reports','reports.kpi','view_reports']],
            'System' => [['Notifications','notifications.index','view_notifications'],['Logs','logs.index','view_logs'],['System health','health.index','view_health']],
        ],
    };
    $roleLabel = ['admin'=>'Administrator','buyer'=>'Buyer','seller'=>'Seller','supplier'=>'Supplier','supplier_worker'=>'Field worker','market_worker'=>'Market worker'][$role] ?? ucfirst($role);
    $user = auth()->user();
    $routeIcons = [
        'dashboard'=>'dash', 'buyer.dashboard'=>'dash', 'seller.dashboard'=>'dash',
        'supplier.dashboard'=>'dash', 'supplier.field.dashboard'=>'dash',
        'monitoring.rfq.index'=>'pulse', 'rfq.index'=>'file', 'buyer.rfq.index'=>'file', 'supplier.rfq.index'=>'file',
        'products.index'=>'box', 'buyer.products.index'=>'box', 'seller.products.index'=>'box',
        'markets.index'=>'store', 'buyer.markets.index'=>'store', 'seller.markets.index'=>'store',
        'procurement.compare.index'=>'cols', 'procurement.orders.index'=>'file', 'orders.index'=>'box', 'seller.orders.index'=>'truck', 'supplier.orders.index'=>'box',
        'users.index'=>'users', 'categories.index'=>'box', 'reports.kpi'=>'chart',
        'supplier.invitations.index'=>'inbox', 'supplier.quotes.index'=>'file',
        'supplier.workers.index'=>'users', 'seller.workers.index'=>'users',
        'supplier.messages.index'=>'mail', 'supplier.field.messages.index'=>'mail',
        'supplier.field.rfq.index'=>'file', 'privacy.index'=>'shield',
        'settings.index'=>'sliders', 'health.index'=>'pulse', 'sla.index'=>'clock',
        'buyer.import-export'=>'download', 'user.profile'=>'user',
    ];
@endphp

<div class="source-ui app"  :class="{ navopen: mobileOpen, collapsed: collapsed }" x-data="{ mobileOpen:false, menuOpen:false, collapsed: localStorage.getItem('48source-sidebar-collapsed') === '1', dark: document.documentElement.dataset.theme === 'dark' }" x-init="$watch('dark', value => window.setAppTheme(value ? 'dark' : 'light'))" x-on:app:theme-changed.window="dark = ($event.detail.theme === 'dark')">
    <button type="button" class="source-exact-backdrop" x-show="mobileOpen" x-cloak x-on:click="mobileOpen=false" aria-label="Close navigation"></button>
    <nav class="sb" aria-label="Primary">
        <div class="sb-top">
            <a class="brand" href="{{ route($role === 'market_worker' ? 'user.profile' : ($role === 'supplier_worker' ? 'supplier.field.dashboard' : ($role === 'supplier' ? 'supplier.dashboard' : ($role === 'seller' ? 'seller.dashboard' : ($role === 'buyer' ? 'buyer.dashboard' : 'dashboard'))))) }}" wire:navigate>
                <span class="mark" aria-hidden="true">48</span><span class="brand-name">48Source</span>
            </a>
        </div>
        <div class="sb-nav">
            @foreach($groups as $group => $items)
                <div class="sb-group">
                    <div class="sb-label">{{ $group }}</div>
                    @foreach($items as [$label,$routeName,$permission])
                        @if(\Illuminate\Support\Facades\Route::has($routeName) && ($permission === null || $user->can($permission)))
                            <a class="sb-item {{ request()->routeIs($routeName) || ($routeName === 'procurement.compare.index' && request()->routeIs('procurement.compare.show')) ? 'on' : '' }}" href="{{ route($routeName) }}" wire:navigate x-on:click="mobileOpen=false" @if(request()->routeIs($routeName) || ($routeName === 'procurement.compare.index' && request()->routeIs('procurement.compare.show'))) aria-current="page" @endif>
                                <x-source.icon :name="$routeIcons[$routeName] ?? 'dash'" />
                                <span>{{ __($label) }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="sb-foot">
            <a class="sb-item {{ request()->routeIs('user.profile') ? 'on' : '' }}" href="{{ route('user.profile') }}" wire:navigate>
                <x-source.icon name="user" />
                <span>Profile & settings</span>
            </a>
            <a class="sb-item" href="{{ route('settings.index') }}" wire:navigate><x-source.icon name="sliders" /><span>{{ __('Settings') }}</span></a>
        </div>
    </nav>
    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn source-exact-mobile" x-on:click="mobileOpen=!mobileOpen" aria-label="Open navigation">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <button type="button" class="icon-btn collapse-btn" x-on:click="collapsed=!collapsed;localStorage.setItem('48source-sidebar-collapsed',collapsed?'1':'0')" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" aria-label="Collapse sidebar"><x-source.icon name="panel" :size="17" /></button>
            <span class="portal">{{ $roleLabel }} workspace</span>
            <nav class="crumbs" aria-label="Breadcrumb">
                <span>{{ config('app.name', '48Source') }}</span><span>/</span>
                <b>{{ request()->route()?->getName() ? str_replace(['.','-'],' ',request()->route()->getName()) : $roleLabel }}</b>
            </nav>
            <div class="tb-search">
                <button type="button" class="source-exact-search" x-on:click="$dispatch('open-search')" aria-label="Search all available records">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <span>Search or jump to a page…</span><kbd>Ctrl K</kbd>
                </button>
            </div>
            <div class="tb-actions">
                @if($role === 'buyer' && $user->can('create_rfqs') && \Illuminate\Support\Facades\Route::has('buyer.rfq.index'))
                    <a class="btn btn-p btn-sm" href="{{ route('buyer.rfq.index') }}" wire:navigate>+ <span>{{ __('New RFQ') }}</span></a>
                @elseif($role === 'admin' && $user->can('create_rfqs') && \Illuminate\Support\Facades\Route::has('rfq.create'))
                    <a class="btn btn-p btn-sm" href="{{ route('rfq.create') }}" wire:navigate>+ <span>{{ __('New RFQ') }}</span></a>
                @endif
                <div class="source-exact-account" x-data="{ language:false }" x-on:click.outside="language=false">
                    <button type="button" class="icon-btn lang-btn" x-on:click="language=!language" aria-label="Language" :aria-expanded="language.toString()">
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <div class="source-exact-menu" x-show="language" x-cloak>
                        @foreach(['en'=>'English','az'=>'Azərbaycanca','ru'=>'Русский','tr'=>'Türkçe'] as $lang => $label)
                            <a href="{{ route('lang.switch', $lang) }}" class="pop-i" wire:navigate>{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="icon-btn" x-on:click="dark=!dark" aria-label="Toggle color theme">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/></svg>
                </button>
                @if(\Illuminate\Support\Facades\Route::has('notifications.index') && $user->can('view_notifications'))
                    <a class="icon-btn" href="{{ route('notifications.index') }}" aria-label="Notifications" wire:navigate>
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0"/></svg>
                    </a>
                @endif
                <div class="source-exact-account" x-on:click.outside="menuOpen=false">
                    <button type="button" class="acct" x-on:click="menuOpen=!menuOpen" aria-label="Account menu" :aria-expanded="menuOpen.toString()">
                        <span class="avatar">{{ strtoupper(substr($user->name,0,1)) }}</span><span class="source-exact-name">{{ $user->name }}</span>
                    </button>
                    <div class="source-exact-menu" x-show="menuOpen" x-cloak>
                        <div class="pop-h">{{ $user->email }}</div>
                        <a class="pop-i" href="{{ route('user.profile') }}" wire:navigate>Profile & settings</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="pop-i" type="submit">Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content" id="main">
            <div class="page">{{ $slot }}</div>
        </main>
    </div>
</div>
