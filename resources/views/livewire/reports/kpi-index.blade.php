<div class="stack">
<header class="ph">
    <div><h1 class="ph-t">{{ __('KPI reports & analytics') }}</h1>
        <p class="ph-d">{{ __('Procurement performance, response speed, supplier participation and RFQ execution.') }}</p></div>
    <div class="ph-a">
        @if(\Illuminate\Support\Facades\Route::has('export.kpi.pdf'))<a class="btn" href="{{ route('export.kpi.pdf') }}" target="_blank" rel="noopener">{{ __('Export PDF') }}</a>@endif
        @if(\Illuminate\Support\Facades\Route::has('export.kpi.excel'))<a class="btn" href="{{ route('export.kpi.excel') }}">{{ __('Export Excel') }}</a>@endif
    </div>
</header>
<div class="tabs" role="tablist" aria-label="{{ __('Report section') }}">
    <button type="button" class="tab {{ $reportType === 'overview' ? 'on' : '' }}" wire:click="$set('reportType','overview')">{{ __('Overview') }}</button>
    <button type="button" class="tab {{ $reportType === 'suppliers' ? 'on' : '' }}" wire:click="$set('reportType','suppliers')">{{ __('Supplier performance') }}</button>
    <button type="button" class="tab {{ $reportType === 'rfqs' ? 'on' : '' }}" wire:click="$set('reportType','rfqs')">{{ __('RFQ execution') }}</button>
</div>
@if($reportType === 'overview')
    <div class="strip">
        @foreach([
            __('Total RFQs') => $aggregate['total_rfqs'] ?? 0,
            __('Completed RFQs') => $aggregate['completed_rfqs'] ?? 0,
            __('Total quotations') => $aggregate['total_quotes'] ?? 0,
            __('Accepted quotes') => $aggregate['accepted_quotes'] ?? 0,
            __('Avg RFQ days') => $aggregate['avg_rfq_execution_days'] ?? 0,
            __('Avg response days') => $aggregate['avg_supplier_response_days'] ?? 0,
        ] as $label => $value)
            <div class="strip-i"><span class="strip-l">{{ $label }}</span><span class="strip-v">{{ $value }}</span></div>
        @endforeach
    </div>
    <div class="grid g-2-1">
        <section class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Supplier response performance') }}</h2></div>
            <div class="card-b">
                <dl class="kv">
                    <dt>{{ __('Sent invitations') }}</dt><dd class="mono">{{ $aggregate['total_invitations'] ?? 0 }}</dd>
                    <dt>{{ __('Responded invitations') }}</dt><dd class="mono">{{ $aggregate['responded_invitations'] ?? 0 }}</dd>
                    <dt>{{ __('Avg response hours') }}</dt><dd class="mono">{{ $aggregate['avg_supplier_response_hours'] ?? 0 }}</dd>
                    <dt>{{ __('Quote acceptance rate') }}</dt><dd class="mono">{{ $aggregate['overall_quote_acceptance_rate'] ?? 0 }}%</dd>
                </dl>
            </div>
        </section>
        <aside class="card">
            <div class="card-h"><h2 class="card-t">{{ __('Fastest supplier responses') }}</h2></div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Supplier') }}</th><th class="num">{{ __('Response (hours)') }}</th></tr></thead>
                <tbody>
                    @forelse($this->topSuppliers as $entry)
                        <tr><td>{{ $entry['supplier']?->company_name ?: $entry['supplier']?->name ?: '—' }}</td>
                            <td class="num mono">{{ data_get($entry,'metrics.avg_response_time_hours',0) }}</td></tr>
                    @empty<tr><td colspan="2"><div class="empty">{{ __('No response data yet') }}</div></td></tr>@endforelse
                </tbody>
            </table></div>
        </aside>
    </div>
    <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Longest RFQ execution times') }}</h2></div>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>{{ __('RFQ') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Execution days') }}</th><th class="num">{{ __('Quotes') }}</th></tr></thead>
        <tbody>
            @forelse($this->slowestRfqs as $item)
                <tr><td><a class="link t1" href="{{ route('rfq.show', $item['rfq_id']) }}">{{ $item['rfq_title'] }}</a></td>
                    <td><span class="badge">{{ ucfirst($item['status']) }}</span></td>
                    <td class="num mono">{{ $item['execution_time_days'] }}</td>
                    <td class="num mono">{{ $item['total_quotes_received'] }}</td></tr>
            @empty<tr><td colspan="4"><div class="empty">{{ __('No completed RFQ data yet') }}</div></td></tr>@endforelse
        </tbody></table></div>
    </section>
@else
    <section class="card"><div class="dt">
        <div class="dt-top">
            <label class="dt-search"><x-source.icon name="search" :size="16"/><span class="sr">{{ __('Search reports') }}</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search records') }}"></label>
            <div class="dt-tools">
                @if($reportType === 'rfqs')
                    <label for="kpi-status" class="sr">{{ __('Status') }}</label>
                    <select id="kpi-status" class="select" wire:model.live="statusFilter">
                        <option value="">{{ __('All statuses') }}</option><option value="open">{{ __('Open') }}</option>
                        <option value="closed">{{ __('Closed') }}</option><option value="awarded">{{ __('Awarded') }}</option>
                    </select>
                @endif
                <label for="kpi-rows" class="sr">{{ __('Rows') }}</label>
                <select id="kpi-rows" class="select" wire:model.live="quantity">
                    <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                </select>
            </div>
        </div>
        @if($reportType === 'suppliers')
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Supplier') }}</th><th class="num">{{ __('Invitations') }}</th><th class="num">{{ __('Response rate') }}</th><th class="num">{{ __('Avg response (h)') }}</th><th class="num">{{ __('Win rate') }}</th></tr></thead>
                <tbody>@forelse($this->suppliers as $entry)
                    <tr><td><a class="link t1" href="{{ route('users.show',$entry->supplier) }}">{{ $entry->supplier->company_name ?: $entry->supplier->name }}</a><div class="t2">{{ $entry->supplier->email }}</div></td>
                        <td class="num mono">{{ data_get($entry->metrics,'total_invitations',0) }}</td>
                        <td class="num mono">{{ data_get($entry->metrics,'response_rate',0) }}%</td>
                        <td class="num mono">{{ data_get($entry->metrics,'avg_response_time_hours',0) }}</td>
                        <td class="num mono">{{ data_get($entry->metrics,'win_rate',0) }}%</td></tr>
                @empty<tr><td colspan="5"><div class="empty">{{ __('No suppliers found') }}</div></td></tr>@endforelse</tbody>
            </table></div><div class="dt-foot">{{ $this->suppliers->links() }}</div>
        @elseif($reportType === 'rfqs')
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('RFQ') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Execution (days)') }}</th><th class="num">{{ __('First quote (h)') }}</th><th class="num">{{ __('Quotations') }}</th></tr></thead>
                <tbody>@forelse($this->rfqs as $rfq)
                    <tr><td><a class="link t1" href="{{ route('rfq.show', $rfq->rfq_id) }}">{{ $rfq->rfq_title }}</a></td>
                        <td><span class="badge">{{ ucfirst($rfq->status) }}</span></td>
                        <td class="num mono">{{ $rfq->execution_time_days }}</td>
                        <td class="num mono">{{ $rfq->time_to_first_quote_hours ?? '—' }}</td>
                        <td class="num mono">{{ $rfq->total_quotes_received }}</td></tr>
                @empty<tr><td colspan="5"><div class="empty">{{ __('No RFQ data found') }}</div></td></tr>@endforelse</tbody>
            </table></div><div class="dt-foot">{{ $this->rfqs->links() }}</div>
        @endif
    </div></section>
@endif
</div>