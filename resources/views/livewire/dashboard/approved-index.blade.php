<div class="stack source-approved-dashboard">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('Procurement overview') }}</h1>
            <p class="ph-d">{{ now()->format('l j F Y') }} · {{ $approved['attentionCount'] }} {{ __('items need attention and') }} {{ count($approved['deadlines']) }} {{ __('upcoming RFQ deadlines') }}.</p>
        </div>
        <div class="ph-a">
            <a class="btn" href="{{ route('reports.kpi') }}" wire:navigate><x-source.icon name="download" :size="16" />{{ __('Reports') }}</a>
            @can('create_rfqs')
                <a class="btn btn-p" href="{{ route('rfq.create') }}" wire:navigate><x-source.icon name="plus" :size="16" />{{ __('New RFQ') }}</a>
            @endcan
        </div>
    </header>

    <div class="source-dashboard-actions">
        @can('create_rfqs')
            <a class="sc" href="{{ route('rfq.create') }}" wire:navigate><span class="si"><x-source.icon name="plus" :size="18" /></span><span><b>{{ __('Create an RFQ') }}</b><span>{{ __('Start a request and invite suppliers') }}</span></span></a>
        @endcan
        <a class="sc" href="{{ route('procurement.compare.index') }}" wire:navigate><span class="si"><x-source.icon name="cols" :size="18" /></span><span><b>{{ __('Compare quotations') }}</b><span>{{ $approved['compare'] }} {{ __('requests ready for a decision') }}</span></span></a>
        <a class="sc" href="{{ route('procurement.orders.index') }}" wire:navigate><span class="si"><x-source.icon name="box" :size="18" /></span><span><b>{{ __('Track orders') }}</b><span>{{ $approved['orders'] }} {{ __('orders in progress') }}</span></span></a>
        @can('view_users')
            <a class="sc" href="{{ route('users.index') }}" wire:navigate><span class="si"><x-source.icon name="users" :size="18" /></span><span><b>{{ __('Manage users') }}</b><span>{{ $approved['users'] }} {{ __('registered accounts') }}</span></span></a>
        @endcan
    </div>

    <div class="strip">
        <a class="strip-i" href="{{ route('rfq.index') }}" wire:navigate><span class="strip-l">{{ __('Open RFQs') }}</span><span class="strip-v">{{ $approved['open'] }}</span><span class="strip-s">{{ __('Collecting supplier quotations') }}</span></a>
        <a class="strip-i" href="{{ route('monitoring.rfq.index') }}" wire:navigate><span class="strip-l">{{ __('RFQs requiring attention') }}</span><span class="strip-v">{{ $approved['attentionCount'] }}</span><span class="strip-s warn">{{ __('Deadlines and award decisions') }}</span></a>
        <a class="strip-i" href="{{ route('rfq.index') }}" wire:navigate><span class="strip-l">{{ __('Pending supplier quotations') }}</span><span class="strip-v">{{ $approved['pending'] }}</span><span class="strip-s">{{ __('Quotes currently pending') }}</span></a>
        <a class="strip-i" href="{{ route('procurement.orders.index') }}" wire:navigate><span class="strip-l">{{ __('Orders needing action') }}</span><span class="strip-v">{{ $approved['orders'] }}</span><span class="strip-s">{{ __('Active procurement orders') }}</span></a>
    </div>

    <div class="grid g-2-1">
        <div class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Requires attention') }}</h2><span class="card-s">{{ __('Workflow alerts, most urgent first') }}</span><span class="sp"></span><a class="link sm" href="{{ route('monitoring.rfq.index') }}" wire:navigate>{{ __('Monitoring') }}</a></div>
                <div class="tbl-wrap"><table class="tbl source-attention-table">
                    <thead><tr><th>{{ __('Record') }}</th><th>{{ __('What needs attention') }}</th><th>{{ __('Due') }}</th><th>{{ __('Action') }}</th></tr></thead>
                    <tbody>
                    @forelse($approved['attention'] as $row)
                        <tr wire:key="attention-{{ $row['id'] }}"><td><a class="t1" href="{{ route('rfq.show',$row['id']) }}" wire:navigate>RFQ #{{ $row['id'] }}</a><div class="t2">{{ $row['title'] }}</div></td>
                            <td><span class="{{ $row['urgency'] === 'bad' ? 'b-bad' : 'muted' }}">{{ __($row['reason']) }}</span></td>
                            <td class="sm">{{ $row['due'] }}<div class="t2">{{ $row['relative'] }}</div></td>
                            <td><a class="btn btn-sm" href="{{ route('rfq.show',$row['id']) }}" wire:navigate>{{ __($row['action']) }}</a></td></tr>
                    @empty<tr><td colspan="4"><div class="empty"><h3>{{ __('No urgent actions') }}</h3><p>{{ __('New deadlines and review tasks will appear here.') }}</p></div></td></tr>@endforelse
                    </tbody>
                </table></div>
            </section>

            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Deadlines approaching') }}</h2><span class="card-s">{{ __('Open quotation windows') }}</span><span class="sp"></span><a class="link sm" href="{{ route('rfq.index') }}" wire:navigate>{{ __('View all') }}</a></div>
                <div class="tbl-wrap"><table class="tbl">
                    <thead><tr><th>{{ __('RFQ') }}</th><th>{{ __('Buyer') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Quotations') }}</th></tr></thead>
                    <tbody>@forelse($approved['deadlines'] as $row)
                        <tr><td><a class="t1" href="{{ route('rfq.show',$row['id']) }}" wire:navigate>{{ $row['title'] }}</a><div class="t2">#{{ $row['id'] }}</div></td>
                            <td>{{ $row['buyer'] }}</td><td class="sm">{{ $row['deadline'] }}</td>
                            <td><span class="mono">{{ $row['quotes'] }}/{{ $row['invited'] }}</span><div class="prog"><i style="width:{{ $row['invited'] > 0 ? min(100,round($row['quotes'] / $row['invited'] * 100)) : 0 }}%"></i></div></td></tr>
                    @empty<tr><td colspan="4"><div class="empty">{{ __('No upcoming deadlines') }}</div></td></tr>@endforelse</tbody>
                </table></div>
            </section>
        </div>
        <div class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Procurement activity') }}</h2><span class="card-s">{{ __('Last 8 weeks') }}</span></div>
                <div class="card-b">
                    <div class="legend"><span><i></i>{{ __('RFQs published') }}</span><span><i class="s2"></i>{{ __('Quotations received') }}</span></div>
                    <svg class="source-dashboard-chart" viewBox="0 0 400 188" role="img" aria-label="{{ __('Requests and quotations created during the past eight weeks') }}">
                        @foreach([0,1,2,3,4] as $tick)
                            <line class="gridline" x1="30" x2="390" y1="{{ 145 - $tick * 31 }}" y2="{{ 145 - $tick * 31 }}" />
                            <text x="24" y="{{ 149 - $tick * 31 }}" text-anchor="end">{{ round($approved['chartMax'] * $tick / 4) }}</text>
                        @endforeach
                        @foreach($approved['weeks'] as $index => $week)
                            @php
                                $base = 38 + $index * 44;
                                $heightA = $week['published'] / $approved['chartMax'] * 124;
                                $heightB = $week['quoted'] / $approved['chartMax'] * 124;
                            @endphp
                            <rect class="published" x="{{ $base }}" y="{{ 145 - $heightA }}" width="13" height="{{ max(0,$heightA) }}" rx="2"/>
                            <rect class="quoted" x="{{ $base + 15 }}" y="{{ 145 - $heightB }}" width="13" height="{{ max(0,$heightB) }}" rx="2"/>
                            <text x="{{ $base + 14 }}" y="164" text-anchor="middle">{{ $week['label'] }}</text>
                        @endforeach
                    </svg>
                    <p class="sm muted">{{ __('Based on actual database timestamps; this week may be incomplete.') }}</p>
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Supplier participation') }}</h2></div>
                <div class="card-b stack">
                    <div class="row"><strong class="h2" style="margin:0">{{ $approved['participation'] }}%</strong><span class="muted sm">{{ __('invitation responses') }}</span></div>
                    <div class="prog" style="height:8px"><i style="width:{{ $approved['participation'] }}%"></i></div>
                    <div class="muted sm">{{ $approved['participationCounts'] }} {{ __('invitations responded') }}</div>
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Recent procurement events') }}</h2></div>
                <div class="card-b">
                    <ul class="tl">
                        @forelse($approved['events'] as $event)
                            <li><div class="t1">{{ $event['description'] }}</div><time>{{ $event['by'] }} · {{ $event['when'] }}</time></li>
                        @empty<li>{{ __('No procurement activity yet.') }}</li>@endforelse
                    </ul>
                </div>
            </section>
        </div>
    </div>
</div>
