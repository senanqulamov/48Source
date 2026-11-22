<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('Available RFQs') }}</h1>
            <p class="ph-d">{{ __('Explore public tenders and requests your company has been invited to.') }}</p>
        </div>
        <div class="ph-a"><a class="btn btn-p" href="{{ route('supplier.invitations.index') }}">{{ __('My invitations') }}</a></div>
    </header>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search">
                    <x-source.icon name="search" :size="16" />
                    <span class="sr">{{ __('Search requests') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by title or description') }}">
                </label>
                <div class="dt-tools">
                    <label for="supplier-rfq-status" class="sm muted">{{ __('Status') }}</label>
                    <select id="supplier-rfq-status" class="select" wire:model.live="statusFilter">
                        <option value="">{{ __('All visible') }}</option>
                        <option value="open">{{ __('Open') }}</option>
                        <option value="closed">{{ __('Closed') }}</option>
                        <option value="awarded">{{ __('Awarded') }}</option>
                    </select>
                    <label for="supplier-rfq-count" class="sm muted">{{ __('Per page') }}</label>
                    <select id="supplier-rfq-count" class="select" wire:model.live="quantity">
                        <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                    </select>
                </div>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Request') }}</th><th>{{ __('Buyer') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Items') }}</th><th>{{ __('Action') }}</th></tr></thead>
                    <tbody>
                    @forelse($this->rows as $rfq)
                        <tr wire:key="supplier-rfq-{{ $rfq->id }}">
                            <td class="mono">#{{ $rfq->id }}</td>
                            <td><a class="t1 link" href="{{ route('supplier.rfq.show', $rfq) }}">{{ $rfq->title }}</a>
                                <div class="t2">{{ \Illuminate\Support\Str::limit($rfq->description, 90) }}</div></td>
                            <td class="sm">{{ $rfq->buyer?->name ?? '—' }}</td>
                            <td class="mono sm">{{ $rfq->deadline?->format('d M Y') ?? '—' }}</td>
                            <td><span class="badge {{ $rfq->status === 'open' ? 'b-info' : ($rfq->status === 'awarded' ? 'b-ok' : '') }}">{{ ucfirst($rfq->status) }}</span></td>
                            <td class="num mono">{{ $rfq->items_count }}</td>
                            <td><a class="btn btn-sm" href="{{ route('supplier.rfq.show', $rfq) }}">{{ __('View RFQ') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><h3>{{ __('No matching RFQs') }}</h3><p>{{ __('Try another search or check your invitations.') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dt-foot">{{ $this->rows->links() }}</div>
        </div>
    </section>
</div>
