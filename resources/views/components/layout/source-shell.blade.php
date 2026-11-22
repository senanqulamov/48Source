@props(['role' => 'admin'])

@php
    // Current routes, never prototype hash routes. Permissions are enforced by Laravel as well.
    $groups = match ($role) {
        'buyer' => [
            'Workspace' => [['Dashboard','buyer.dashboard','view_dashboard'],['My RFQs','buyer.rfq.index','view_rfqs']],
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
            'Operations' => [['Orders','supplier.orders.index','view_orders'],['Field workers','supplier.workers.index','view_dashboard'],['Messages','supplier.messages.index',null]],
        ],
        'supplier_worker' => [
            'Workspace' => [['Dashboard','supplier.field.dashboard',null],['Assigned RFQs','supplier.field.rfq.index',null],['Messages','supplier.field.messages.index',null]],
        ],
        'market_worker' => ['My account' => [['Profile','user.profile',null]]],
        default => [
            'Workspace' => [['Dashboard','dashboard','view_dashboard'],['Monitoring & SLA','monitoring.rfq.index','view_monitoring']],
            'Sourcing' => [['RFQs','rfq.index','view_rfqs'],['Purchase orders','orders.index','view_orders'],['Products','products.index','view_products'],['Markets','markets.index','view_markets']],
            'Network' => [['Users','users.index','view_users'],['Categories','categories.index','view_categories']],
            'Insights' => [['KPI reports','reports.kpi','view_reports'],['SLA tracker','sla.index','manage_sla']],
            'Administration' => [['Roles & permissions','privacy.index','manage_roles'],['Settings','settings.index','view_settings'],['System health','health.index','view_health']],
        ],
    };
    $roleLabel = ['admin'=>'Administrator','buyer'=>'Buyer','seller'=>'Seller','supplier'=>'Supplier','supplier_worker'=>'Field worker','market_worker'=>'Market worker'][$role] ?? ucfirst($role);
    $user = auth()->user();
@endphp

<div class="source-ui" x-data="{ mobileOpen: false, accountOpen: false }">
    <button class="source-backdrop" :class="{ 'source-open': mobileOpen }" x-on:click="mobileOpen=false" aria-label="Close navigation"></button>
    <aside class="source-sidebar" :class="{ 'source-open': mobileOpen }" aria-label="Main navigation">
        <a href="{{ route($role === 'market_worker' ? 'user.profile' : ($role === 'supplier_worker' ? 'supplier.field.dashboard' : ($role === 'supplier' ? 'supplier.dashboard' : ($role === 'seller' ? 'seller.dashboard' : ($role === 'buyer' ? 'buyer.dashboard' : 'dashboard'))))) }}" class="source-brand">
            <span class="source-mark">48</span><span>48Source</span>
        </a>
        <nav class="source-nav" aria-label="{{ $roleLabel }} navigation">
            @foreach($groups as $group => $items)
                <div class="source-nav-group">{{ $group }}</div>
                @foreach($items as [$label,$routeName,$permission])
                    @if(\Illuminate\Support\Facades\Route::has($routeName) && ($permission === null || $user->can($permission)))
                        <a class="source-nav-link" href="{{ route($routeName) }}" @if(request()->routeIs($routeName)) aria-current="page" @endif>
                            <span class="source-dot" aria-hidden="true"></span><span>{{ $label }}</span>
                        </a>
                    @endif
                @endforeach
            @endforeach
        </nav>
        <div class="source-side-foot">
            <a class="source-nav-link" href="{{ route('user.profile') }}"><span class="source-dot" aria-hidden="true"></span>Profile</a>
            <span class="block px-3 py-2 text-xs">{{ $roleLabel }} workspace</span>
        </div>
    </aside>
    <div class="source-workspace">
        <header class="source-topbar">
            <button type="button" class="source-icon-btn source-mobile-toggle" x-on:click="mobileOpen=true" aria-label="Open navigation">&#9776;</button>
            <span class="source-portal">{{ $roleLabel }} workspace</span>
            <span class="source-spacer"></span>
            @if(in_array($role, ['buyer','admin'], true) && $user->can('create_rfqs') && \Illuminate\Support\Facades\Route::has('rfq.create'))
                <a class="source-primary" href="{{ route('rfq.create') }}">+ New RFQ</a>
            @endif
            <button type="button" class="source-icon-btn" x-on:click="$dispatch('openSearch')" aria-label="Search" title="Search">&#8981;</button>
            <div class="source-menu" x-on:click.outside="accountOpen=false">
                <button type="button" class="source-icon-btn" x-on:click="accountOpen=!accountOpen" :aria-expanded="accountOpen.toString()" aria-label="Account menu">
                    <span class="source-avatar">{{ strtoupper(substr($user->name,0,1)) }}</span>
                </button>
                <div class="source-menu-panel" x-show="accountOpen" x-cloak>
                    <div class="px-2 py-2 text-sm font-semibold">{{ $user->name }}</div>
                    <div class="px-2 pb-2 text-xs break-all">{{ $user->email }}</div>
                    <a href="{{ route('user.profile') }}">Profile</a>
                    <form action="{{ route('logout') }}" method="POST">@csrf
                        <button type="submit" class="source-signout">Sign out</button>
                    </form>
                </div>
            </div>
        </header>
        <main class="source-body" id="main">{{ $slot }}</main>
    </div>
</div>
