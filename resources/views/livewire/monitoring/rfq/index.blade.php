<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('RFQ monitoring') }}</h1>
            <p class="ph-d">{{ __('Track deadlines, exceptions, workflow status and outstanding sourcing activity.') }}</p></div>
        <div class="ph-a">
            @can('create_rfqs')<livewire:monitoring.rfq.create @created="$refresh" />@endcan
        </div>
    </header>
    <div class="strip">
        <div class="strip-i"><span class="strip-l">{{ __('Open RFQs') }}</span><span class="strip-v">{{ $metrics['open'] ?? 0 }}</span><span class="strip-s">{{ __('Collecting quotations') }}</span></div>
        <div class="strip-i"><span class="strip-l">{{ __('Draft') }}</span><span class="strip-v">{{ $metrics['draft'] ?? 0 }}</span><span class="strip-s">{{ __('Not published') }}</span></div>
        <div class="strip-i"><span class="strip-l">{{ __('Closed') }}</span><span class="strip-v">{{ $metrics['closed'] ?? 0 }}</span><span class="strip-s">{{ __('Quotation period completed') }}</span></div>
        <div class="strip-i"><span class="strip-l">{{ __('Due within 3 days') }}</span><span class="strip-v">{{ $metrics['due_3_days'] ?? 0 }}</span><span class="strip-s">{{ __('Approaching deadline') }}</span></div>
        <div class="strip-i"><span class="strip-l">{{ __('Overdue') }}</span><span class="strip-v">{{ $metrics['overdue'] ?? 0 }}</span><span class="strip-s">{{ __('Deadline passed') }}</span></div>
    </div>
    <section class="card">
        <div class="card-h"><h2 class="card-t">{{ __('Attention & deadlines') }}</h2><span class="card-s">{{ __('Live RFQ data') }}</span></div>
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search"><x-source.icon name="search" :size="16"/><span class="sr">{{ __('Search RFQs') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search title, reference or status') }}"></label>
                <div class="dt-tools">
                    <label for="monitor-pagesize" class="sr">{{ __('Rows per page') }}</label>
                    <select id="monitor-pagesize" class="select" wire:model.live="quantity"><option value="10">10</option><option value="20">20</option><option value="50">50</option></select>
                </div>
            </div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('RFQ') }}</th><th>{{ __('Status') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('SLA risk') }}</th><th class="num">{{ __('Items') }}</th><th class="num">{{ __('Quotes') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                @forelse($this->rows as $rfq)
                    @php
                        $overdue = $rfq->status === 'open' && $rfq->deadline && $rfq->deadline->isPast();
                        $soon = !$overdue && $rfq->status === 'open' && $rfq->deadline && $rfq->deadline->isBefore(now()->addDays(3));
                    @endphp
                    <tr wire:key="monitor-{{ $rfq->id }}">
                        <td class="mono">#{{ $rfq->id }}</td>
                        <td><a class="t1 link" href="{{ route('monitoring.rfq.show', $rfq) }}">{{ $rfq->title }}</a><div class="t2">{{ $rfq->created_at?->format('d M Y') }}</div></td>
                        <td><span class="badge {{ $rfq->status === 'open' ? 'b-info' : ($rfq->status === 'awarded' ? 'b-ok' : '') }}">{{ ucfirst($rfq->status) }}</span></td>
                        <td class="mono sm">{{ $rfq->deadline?->format('d M Y') ?? '—' }}</td>
                        <td>
                            @if($overdue)<span class="badge b-bad">{{ __('Overdue') }}</span>
                            @elseif($soon)<span class="badge b-warn">{{ __('Due soon') }}</span>
                            @else<span class="muted sm">—</span>@endif
                        </td>
                        <td class="num mono">{{ $rfq->items_count }}</td>
                        <td class="num mono">{{ $rfq->quotes_count }}</td>
                        <td><a class="btn btn-sm" href="{{ route('monitoring.rfq.show', $rfq) }}">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">{{ __('No requests match the search.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="dt-foot">{{ $this->rows->links() }}</div>
        </div>
    </section>
    <div class="note">{{ __('The deadline indicators use current RFQ dates; the planned post-close award SLA is not implemented yet.') }}</div>
</div>