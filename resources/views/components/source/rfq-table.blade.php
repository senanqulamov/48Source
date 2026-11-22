@props([
    'rows',
    'detailRoute' => 'rfq.show',
    'statusProperty' => 'status',
    'searchProperty' => 'search',
    'buyer' => false,
    'activeStatus' => 'all',
    'statusCounts' => [],
    'buyerOptions' => [],
    'sortState' => ['column'=>'created_at','direction'=>'desc'],
    'searchValue' => null,
    'typeFilterValue' => 'all',
    'buyerFilterValue' => 'all',
])

@php
    // The same server-driven table powers both buyer and administrator.
    $counts = $statusCounts;
    $totalCount = array_sum($counts);
    $active = $activeStatus ?: 'all';
    $hasFilters = filled($searchValue) || $active !== 'all'
        || $typeFilterValue !== 'all'
        || (!$buyer && $buyerFilterValue !== 'all');
    $columns = ['id' => 'Reference', 'title' => 'Title', 'buyer' => 'Buyer',
        'request_type' => 'Type', 'status' => 'Status', 'deadline' => 'Deadline',
        'items' => 'Items', 'quotes' => 'Quotes', 'created_at' => 'Created'];
@endphp

<div class="stack source-rfq-table" aria-label="{{ __('Requests for quotation') }}"
     x-data="{ columns: { id:true,title:true,buyer:true,request_type:true,status:true,deadline:true,items:true,quotes:true,created_at:true }, columnsOpen:false }">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ $buyer ? __('My RFQs') : __('Requests for quotation') }}</h1>
            <p class="ph-d">{{ __('Manage requests, review incoming supplier quotations and track deadlines.') }}</p>
        </div>
        <div class="ph-a">
            @can('create_rfqs')
                @if($buyer)
                    <button type="button" class="btn btn-p" wire:click="$dispatch('buyer::rfq::create::open')"><x-source.icon name="plus" :size="16" />{{ __('New RFQ') }}</button>
                @else
                    <a class="btn btn-p" href="{{ route('rfq.create') }}" wire:navigate><x-source.icon name="plus" :size="16" />{{ __('New RFQ') }}</a>
                @endif
            @endcan
        </div>
    </header>
    <section class="card" aria-label="{{ __('RFQ listing') }}">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search">
                    <x-source.icon name="search" :size="16" />
                    <span class="sr">{{ __('Search requests') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="{{ $searchProperty }}"
                           placeholder="{{ __('Search reference, title or buyer') }}" />
                </label>
                <div class="dt-tools">
                    <div class="seg" role="group" aria-label="{{ __('Filter by status') }}">
                        @foreach(['all'=>'All','draft'=>'Draft','open'=>'Open','closed'=>'Closed','awarded'=>'Awarded','cancelled'=>'Cancelled'] as $status => $label)
                            <button type="button" class="{{ $active === $status ? 'on' : '' }}"
                                aria-pressed="{{ $active === $status ? 'true' : 'false' }}"
                                @if($buyer && $status === 'all') wire:click="clearStatusFilter"
                                @else wire:click="$set('{{ $statusProperty }}', '{{ $status }}')" @endif>
                                {{ __($label) }}<span class="n">{{ $status === 'all' ? $totalCount : ($counts[$status] ?? 0) }}</span>
                            </button>
                        @endforeach
                    </div>
                    <label class="source-filter">
                        <span class="sr">{{ __('Request type') }}</span>
                        <select class="fbtn" wire:model.live="typeFilter">
                            <option value="all">{{ __('All types') }}</option>
                            <option value="public">{{ __('Public tender') }}</option>
                            <option value="internal">{{ __('Internal request') }}</option>
                        </select>
                    </label>
                    @unless($buyer)
                        <label class="source-filter">
                            <span class="sr">{{ __('Buyer') }}</span>
                            <select class="fbtn" wire:model.live="buyerFilter">
                                <option value="all">{{ __('All buyers') }}</option>
                                @foreach($buyerOptions as $buyerId => $buyerName)
                                    <option value="{{ $buyerId }}">{{ $buyerName }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endunless
                    <div class="source-column-chooser" x-on:click.outside="columnsOpen=false">
                        <button type="button" class="btn btn-sm"
                            x-on:click="columnsOpen=!columnsOpen" :aria-expanded="columnsOpen.toString()">
                            <x-source.icon name="sliders" :size="14" />{{ __('Columns') }}
                        </button>
                        <div class="pop source-columns-pop" x-show="columnsOpen" x-cloak>
                            <div class="pop-h">{{ __('Choose columns') }}</div>
                            @foreach($columns as $field => $label)
                                <label class="pop-i">
                                    <input class="chk" type="checkbox" x-model="columns.{{ $field }}"
                                        @if(in_array($field, ['id','title','status','deadline'])) disabled @endif />
                                    <span>{{ __($label) }}</span>
                                </label>
                            @endforeach
                            <button type="button" class="pop-i" x-on:click="Object.keys(columns).forEach(key => columns[key]=true)">{{ __('Show all') }}</button>
                        </div>
                    </div>
                    <label for="source-rfq-size-{{ $buyer ? 'buyer' : 'admin' }}" class="sr">{{ __('Rows per page') }}</label>
                    <select id="source-rfq-size-{{ $buyer ? 'buyer' : 'admin' }}" class="select source-page-size" wire:model.live="quantity">
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option>
                    </select>
                </div>
            </div>
            @if($hasFilters)
                <div class="dt-chips">
                    <span class="sm muted">{{ $rows->total() }} {{ __('matching RFQs') }}</span>
                    @if(filled($this->search))<span class="fchip">{{ __('Search') }}: {{ $this->search }}</span>@endif
                    @if($active !== 'all')<span class="fchip">{{ __('Status') }}: {{ ucfirst($active) }}</span>@endif
                    @if($this->typeFilter !== 'all')<span class="fchip">{{ __('Type') }}: {{ ucfirst($this->typeFilter) }}</span>@endif
                    <button type="button" class="btn btn-sm btn-g" wire:click="clearTableFilters">{{ __('Clear all') }}</button>
                </div>
            @endif
            <div class="tbl-wrap">
                <table class="tbl source-rfq-grid">
                    <thead><tr>
                        @foreach(['id'=>'Reference','title'=>'Title','buyer'=>'Buyer','request_type'=>'Type','status'=>'Status','deadline'=>'Deadline','items'=>'Items','quotes'=>'Quotes','created_at'=>'Created'] as $key => $label)
                            <th scope="col" @if(in_array($key,['buyer','request_type','items','quotes','created_at'])) x-show="columns.{{ $key }}" @endif
                                @if(in_array($key,['id','title','request_type','status','deadline','created_at']))
                                    aria-sort="{{ ($sortState['column'] ?? '') === $key ? (($this->sort['direction'] ?? '') === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                @endif>
                                @if(in_array($key,['id','title','request_type','status','deadline','created_at']))
                                    <button type="button" wire:click="changeSort('{{ $key }}')"
                                        class="{{ ($this->sort['column'] ?? '') === $key ? 'on' : '' }}">{{ __($label) }}
                                        <x-source.icon name="sort" :size="12" />
                                    </button>
                                @else
                                    {{ __($label) }}
                                @endif
                            </th>
                        @endforeach
                        <th scope="col">{{ __('Action') }}</th>
                    </tr></thead>
                    <tbody wire:loading.class="source-rfq-loading">
                    @forelse($rows as $rfq)
                        @php
                            $tone = match(strtolower($rfq->status)) {
                                'open'=>'b-info','awarded'=>'b-ok','closed'=>'b-warn','cancelled'=>'b-bad',default=>'',
                            };
                        @endphp
                        <tr wire:key="source-rfq-{{ $rfq->id }}">
                            <td class="mono"><a href="{{ route($detailRoute, $rfq) }}" wire:navigate>#{{ $rfq->id }}</a></td>
                            <td><a class="t1 link" href="{{ route($detailRoute, $rfq) }}" wire:navigate>{{ $rfq->title }}</a><div class="t2">{{ $rfq->description ? \Illuminate\Support\Str::limit($rfq->description, 86) : '' }}</div></td>
                            <td x-show="columns.buyer" class="sm">{{ $rfq->buyer?->name ?? '—' }}</td>
                            <td x-show="columns.request_type"><span class="tag">{{ $rfq->request_type === 'public' ? __('Public tender') : __('Internal request') }}</span></td>
                            <td><span class="badge {{ $tone }}">{{ ucfirst($rfq->status) }}</span></td>
                            <td class="mono">{{ $rfq->deadline?->format('d M Y') ?? '—' }}</td>
                            <td x-show="columns.items" class="num mono">{{ $rfq->items_count }}</td>
                            <td x-show="columns.quotes" class="num mono">{{ $rfq->quotes_count }}/{{ $rfq->supplier_invitations_count }}</td>
                            <td x-show="columns.created_at" class="sm muted">{{ $rfq->created_at?->format('d M Y') }}</td>
                            <td><a class="btn btn-sm" href="{{ route($detailRoute, $rfq) }}" wire:navigate>{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10">
                            <div class="empty">
                                <span class="ei"><x-source.icon name="search" :size="22" /></span>
                                <h3>{{ __('No matching RFQs') }}</h3>
                                <p>{{ __('Try removing search terms or resetting filters.') }}</p>
                                <button type="button" class="btn btn-sm" wire:click="clearTableFilters">{{ __('Clear all filters') }}</button>
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
