@props([
    'rows',
    'detailRoute' => 'rfq.show',
    'statusProperty' => 'status',
    'searchProperty' => 'search',
    'buyer' => false,
])

@php
    $activeStatus = $statusProperty === 'status' ? ($this->status ?? 'all') : ($this->statusFilter ?? 'all');
    $activeStatus = $activeStatus ?: 'all';
@endphp

<div class="stack" aria-label="Requests for quotation">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ $buyer ? __('My RFQs') : __('Requests for quotation') }}</h1>
            <p class="ph-d">{{ __('Manage requests, review incoming supplier quotations and track deadlines.') }}</p>
        </div>
        <div class="ph-a">
            @can('create_rfqs')
                @if($buyer)
                    <button type="button" class="btn btn-p" wire:click="$dispatch('buyer::rfq::create::open')">+ {{ __('New RFQ') }}</button>
                @else
                    <a class="btn btn-p" href="{{ route('rfq.create') }}">+ {{ __('New RFQ') }}</a>
                @endif
            @endcan
        </div>
    </header>

    <section class="card" aria-label="RFQ listing">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search">
                    <span class="sr">{{ __('Search requests') }}</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="{{ $searchProperty }}" placeholder="{{ __('Search RFQs by title or description') }}">
                </label>
                <div class="dt-tools">
                    <div class="seg" role="group" aria-label="{{ __('Filter by status') }}">
                        @foreach(['all'=>__('All'), 'draft'=>__('Draft'), 'open'=>__('Open'), 'closed'=>__('Closed'), 'awarded'=>__('Awarded'), 'cancelled'=>__('Cancelled')] as $status => $label)
                            <button type="button" class="{{ $activeStatus === $status ? 'on' : '' }}" aria-pressed="{{ $activeStatus === $status ? 'true' : 'false' }}" wire:click="$set('{{ $statusProperty }}', {{ $status === 'all' && $statusProperty !== 'status' ? 'null' : '\'' . $status . '\'' }})">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th scope="col">{{ __('Reference') }}</th>
                        <th scope="col">{{ __('Request') }}</th>
                        <th scope="col">{{ __('Type') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Deadline') }}</th>
                        <th scope="col" class="num">{{ __('Items') }}</th>
                        <th scope="col" class="num">{{ __('Quotes') }}</th>
                        <th scope="col">{{ __('Created') }}</th>
                        <th scope="col">{{ __('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $rfq)
                        @php
                            $tone = match(strtolower($rfq->status)) {
                                'open' => 'b-info', 'awarded' => 'b-ok', 'closed' => 'b-warn',
                                'cancelled' => 'b-bad', default => '',
                            };
                        @endphp
                        <tr wire:key="source-rfq-{{ $rfq->id }}">
                            <td class="mono">#{{ $rfq->id }}</td>
                            <td>
                                <a class="t1 link" href="{{ route($detailRoute, $rfq) }}">{{ $rfq->title }}</a>
                                @if($rfq->description)<div class="t2 trunc">{{ $rfq->description }}</div>@endif
                            </td>
                            <td><span class="tag">{{ $rfq->request_type === 'public' ? __('Public tender') : __('Internal request') }}</span></td>
                            <td><span class="badge {{ $tone }}">{{ ucfirst($rfq->status) }}</span></td>
                            <td class="mono">{{ $rfq->deadline?->format('d M Y') ?? '—' }}</td>
                            <td class="num mono">{{ $rfq->items_count }}</td>
                            <td class="num mono">{{ $rfq->quotes_count }}</td>
                            <td class="sm muted">{{ $rfq->created_at?->format('d M Y') }}</td>
                            <td>
                                <a class="btn btn-sm" href="{{ route($detailRoute, $rfq) }}">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">
                            <div class="empty">
                                <h3>{{ __('No matching RFQs') }}</h3>
                                <p>{{ __('Try adjusting the search or status filter.') }}</p>
                            </div>
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dt-foot">{{ $rows->links() }}</div>
        </div>
    </section>
</div>
