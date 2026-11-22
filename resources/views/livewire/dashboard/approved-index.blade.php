<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('Procurement overview') }}</h1>
            <p class="ph-d">{{ now()->format('l j F Y') }} · {{ __('System-wide sourcing and procurement activity') }}</p>
        </div>
        <div class="ph-a">
            @can('create_rfqs')
                <a class="btn btn-p" href="{{ route('rfq.create') }}">+ {{ __('New RFQ') }}</a>
            @endcan
        </div>
    </header>
    <div class="strip">
        <a class="strip-i" href="{{ route('rfq.index') }}"><span class="strip-l">{{ __('Open RFQs') }}</span><span class="strip-v">{{ $rfqStats['openRfqs'] ?? 0 }}</span><span class="strip-s">{{ __('Collecting supplier quotations') }}</span></a>
        <a class="strip-i" href="{{ route('monitoring.rfq.index') }}"><span class="strip-l">{{ __('RFQs requiring attention') }}</span><span class="strip-v">{{ $rfqStats['pendingApprovals'] ?? 0 }}</span><span class="strip-s">{{ __('Pending approvals') }}</span></a>
        <div class="strip-i"><span class="strip-l">{{ __('Pending supplier quotations') }}</span><span class="strip-v">{{ $rfqStats['pendingQuotes'] ?? 0 }}</span><span class="strip-s">{{ __('Quotes currently pending') }}</span></div>
        <a class="strip-i" href="{{ route('orders.index') }}"><span class="strip-l">{{ __('Orders') }}</span><span class="strip-v">{{ $rfqStats['purchaseOrders'] ?? 0 }}</span><span class="strip-s">{{ __('Existing marketplace orders') }}</span></a>
    </div>

    <div class="grid g-2-1">
        <div class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Requires attention') }}</h2><span class="card-s">{{ __('Requests approaching a deadline') }}</span><span class="sp"></span><a href="{{ route('monitoring.rfq.index') }}" class="link sm">{{ __('Monitoring') }}</a></div>
                @php
                    $attention = collect($recentRfqs ?? [])->filter(function ($item) {
                        return strtolower($item['status'] ?? '') === 'open' && !empty($item['deadline']);
                    })->take(5);
                @endphp
                @if($attention->isEmpty())
                    <div class="empty"><h3>{{ __('No recent open requests') }}</h3><p>{{ __('Newly active requests will be visible here.') }}</p></div>
                @else
                    <ul class="list">
                        @foreach($attention as $record)
                            <li>
                                <div class="grow"><a href="{{ route('rfq.show', $record['id']) }}" class="link">{{ $record['title'] }}</a><div class="muted sm">#{{ $record['id'] }} · {{ __('Deadline') }}: {{ $record['deadline'] }}</div></div>
                                <span class="badge b-info">{{ ucfirst($record['status']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Recently active requests') }}</h2><span class="sp"></span><a class="link sm" href="{{ route('rfq.index') }}">{{ __('View all') }}</a></div>
                <div class="tbl-wrap">
                    <table class="tbl compact">
                        <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Quotes') }}</th></tr></thead>
                        <tbody>
                        @forelse($recentRfqs ?? [] as $request)
                            <tr>
                                <td><a class="t1" href="{{ route('rfq.show', $request['id']) }}">{{ $request['title'] }}</a><div class="t2">#{{ $request['id'] }} · {{ $request['buyer'] }}</div></td>
                                <td><span class="badge">{{ ucfirst($request['status']) }}</span></td>
                                <td class="num mono">{{ $request['quotes_count'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><div class="empty">{{ __('No requests yet.') }}</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('RFQs by status') }}</h2><span class="card-s">{{ __('Real database totals') }}</span></div>
                <div class="card-b stack">
                    @php
                        $totalRfqs = max(1, (int) ($rfqStats['totalRfqs'] ?? 0));
                    @endphp
                    @foreach($rfqsByStatus ?? [] as $group)
                        <div class="hb">
                            <span class="sm">{{ $group['label'] }}</span>
                            <div class="prog"><i style="width:{{ round(($group['value'] ?? 0) / $totalRfqs * 100, 1) }}%"></i></div>
                            <span class="mono sm num">{{ $group['value'] ?? 0 }}</span>
                        </div>
                    @endforeach
                    @if(empty($rfqsByStatus))
                        <p class="muted sm">{{ __('No RFQ data available.') }}</p>
                    @endif
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Supplier participation') }}</h2></div>
                <div class="card-b">
                    <div class="row"><span class="strip-v" style="color:var(--text)">{{ $rfqStats['totalSuppliers'] ?? 0 }}</span><span class="muted">{{ __('registered suppliers') }}</span></div>
                    <p class="muted sm" style="margin-top:8px">{{ $rfqStats['activeSuppliers'] ?? 0 }} {{ __('suppliers with accepted invitations') }}</p>
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Procurement activity') }}</h2></div>
                <div class="card-b">
                    <div class="row" style="justify-content:space-between"><span>{{ __('Total quotations') }}</span><b>{{ $rfqStats['totalQuotes'] ?? 0 }}</b></div>
                    <div class="row" style="justify-content:space-between;margin-top:12px"><span>{{ __('Workflow events today') }}</span><b>{{ $rfqStats['eventsToday'] ?? 0 }}</b></div>
                    <div class="row" style="justify-content:space-between;margin-top:12px"><span>{{ __('Awarded RFQs') }}</span><b>{{ $rfqStats['awardedRfqs'] ?? 0 }}</b></div>
                </div>
            </section>
        </div>
    </div>
</div>
