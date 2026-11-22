<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('My quotations') }}</h1>
            <p class="ph-d">{{ __('Track submitted quotations and their outcomes.') }}</p>
        </div>
        <div class="ph-a"><a class="btn btn-p" href="{{ route('supplier.invitations.index') }}">{{ __('View RFQ invitations') }}</a></div>
    </header>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search">
                    <x-source.icon name="search" :size="16" />
                    <span class="sr">{{ __('Search quotations') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by RFQ title') }}" />
                </label>
                <div class="dt-tools">
                    <label for="quote-status" class="sm muted">{{ __('Status') }}</label>
                    <select id="quote-status" class="select" wire:model.live="statusFilter">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="submitted">{{ __('Submitted') }}</option>
                        <option value="accepted">{{ __('Accepted') }}</option>
                        <option value="rejected">{{ __('Rejected') }}</option>
                    </select>
                </div>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr>
                        <th>{{ __('Quote') }}</th>
                        <th>{{ __('RFQ') }}</th>
                        <th>{{ __('Buyer') }}</th>
                        <th class="num">{{ __('Total amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Validity') }}</th>
                        <th>{{ __('Submitted') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($this->rows as $quote)
                        <tr wire:key="quote-{{ $quote->id }}">
                            <td class="mono">#{{ $quote->id }}</td>
                            <td>
                                <span class="t1">{{ $quote->request?->title ?? __('Request unavailable') }}</span>
                                <div class="t2 mono">RFQ #{{ $quote->request_id }}</div>
                            </td>
                            <td class="sm">{{ $quote->request?->buyer?->name ?? '—' }}</td>
                            <td class="num mono">{{ $quote->currency ?: '' }} {{ number_format((float) ($quote->total_amount ?? $quote->total_price ?? 0), 2) }}</td>
                            <td><span class="badge {{ in_array($quote->status,['accepted','won']) ? 'b-ok' : ($quote->status === 'rejected' ? 'b-bad' : 'b-info') }}">{{ ucfirst($quote->status) }}</span></td>
                            <td class="mono sm">{{ $quote->valid_until?->format('d M Y') ?? '—' }}</td>
                            <td class="mono sm">{{ $quote->submitted_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><h3>{{ __('No quotations found') }}</h3><p>{{ __('Respond to an RFQ invitation to submit your first quote.') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dt-foot">{{ $this->rows->links() }}</div>
        </div>
    </section>
</div>
