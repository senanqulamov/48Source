<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('Compare quotations') }}</h1>
            <p class="ph-d">{{ __('Choose an RFQ to review actual supplier prices side by side.') }}</p>
        </div>
    </header>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search"><x-source.icon name="search" :size="16"/>
                    <span class="sr">{{ __('Search requests') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search reference or title') }}"/>
                </label>
                <div class="dt-tools">
                    <label class="sr" for="comparison-status">{{ __('Status') }}</label>
                    <select class="select" id="comparison-status" wire:model.live="status">
                        <option value="all">{{ __('All statuses') }}</option>
                        @foreach(['open','closed','awarded'] as $value)
                            <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th scope="col">{{ __('Reference') }}</th><th scope="col">{{ __('RFQ') }}</th>
                    <th scope="col">{{ __('Buyer') }}</th><th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Quotations') }}</th><th scope="col">{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse($requests as $rfq)
                        <tr wire:key="comparison-rfq-{{ $rfq->id }}">
                            <td class="mono">#{{ $rfq->id }}</td>
                            <td><a href="{{ route('procurement.compare.show',$rfq) }}" wire:navigate class="t1 link">{{ $rfq->title }}</a>
                                <div class="t2">{{ $rfq->deadline?->format('d M Y') ?? '—' }}</div></td>
                            <td>{{ $rfq->buyer?->name ?? '—' }}</td>
                            <td><span class="badge {{ $rfq->status === 'awarded' ? 'b-ok' : ($rfq->status === 'closed' ? 'b-warn' : 'b-info') }}">{{ ucfirst($rfq->status) }}</span></td>
                            <td class="mono">{{ $rfq->quotes_count }}</td>
                            <td><a class="btn btn-sm" href="{{ route('procurement.compare.show',$rfq) }}" wire:navigate>{{ __('Compare') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">
                            <h3>{{ __('No matching quotations') }}</h3>
                            <p>{{ __('RFQs appear here when suppliers submit quotations.') }}</p>
                        </div></td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="dt-foot">{{ $requests->links() }}</div>
        </div>
    </section>
</div>