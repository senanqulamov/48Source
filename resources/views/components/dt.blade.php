@props(['lw', 'rows', 'id' => 'dt', 'search' => 'Search', 'label' => 'Search', 'noun' => 'records', 'all' => null, 'quick' => null, 'quickLabel' => 'Status', 'facets' => [], 'cols' => [], 'keys' => null, 'export' => false, 'guide' => []])
{{-- The prototype's table card: search, quick filter, facet filters, column chooser, export, chips, table, footer, bulk bar.
     $quick: value => [label, count]; $facets: key => [label, [value => label]]; $cols: key => label of columns the user may hide. --}}
@php
    $all ??= $rows->total();
    $active = collect($lw->f)->map(fn ($v) => is_array($v) ? count($v) : 0)->sum();
    $pages = function (int $cur, int $n) { $o = []; for ($i = 1; $i <= $n; $i++) { if ($i === 1 || $i === $n || abs($i - $cur) <= 1) { $o[] = $i; } elseif (end($o) !== '…') { $o[] = '…'; } } return $o; };
    $pageKeys = $keys ? collect($keys)->map(fn ($k) => (string) $k)->all() : [];
    $nsel = count(array_intersect($pageKeys, $lw->selected));
@endphp
<section {{ $attributes->class(['card', 'dt', 'dt-' . $id]) }}>
    @if ($lw->hide)<style>@foreach ($lw->hide as $h).dt.dt-{{ $id }} table.tbl [data-c="{{ preg_replace('/[^a-z0-9_]/i', '', $h) }}"]{display:none!important}@endforeach</style>@endif
    <div class="dt-top">
        <div class="dt-search"><x-icon name="search" /><input type="search" placeholder="{{ $search }}" aria-label="{{ $label }}" wire:model.live.debounce.300ms="q">@if ($lw->q !== '')<button type="button" class="dt-clear" wire:click="$set('q', '')" aria-label="Clear search"><x-icon name="x" :size="14" /></button>@endif<kbd>/</kbd></div>
        <div class="dt-tools">
            @if ($quick)
                <div class="seg" role="group" aria-label="{{ $quickLabel }}">
                    @foreach ($quick as $v => [$l, $n])
                        <button type="button" class="{{ $lw->status === (string) $v ? 'on' : '' }}" aria-pressed="{{ $lw->status === (string) $v ? 'true' : 'false' }}" wire:click="setStatus('{{ $v }}')">{{ $l }}<span class="n">{{ $n }}</span></button>
                    @endforeach
                </div>
            @endif
            @foreach ($facets as $k => [$fl, $opts])
                @php $n = count((array) ($lw->f[$k] ?? [])); @endphp
                <span x-data="pop({ w: 270, left: true })" style="display:contents">
                    <button type="button" class="fbtn {{ $n ? 'on' : '' }}" x-on:click="toggle($el)" aria-haspopup="true">{{ $fl }}@if ($n)<span class="n">{{ $n }}</span>@endif<x-icon name="down" :size="14" /></button>
                    <div class="pop" x-ref="p" x-show="open" x-cloak wire:ignore.self :style="pos">
                        <div class="pop-h">{{ $fl }}<span class="sp"></span><button type="button" class="link sm" wire:click="clearFacet('{{ $k }}')">Clear</button></div>
                        @forelse ($opts as $v => $l)
                            @php $onF = in_array((string) $v, array_map('strval', (array) ($lw->f[$k] ?? [])), true); @endphp
                            <label class="pop-i" style="cursor:pointer" wire:key="f-{{ $k }}-{{ $v }}-{{ $onF ? 1 : 0 }}"><input type="checkbox" class="chk" wire:click="toggleFacet('{{ $k }}', '{{ $v }}')" @checked($onF)><span style="flex:1;min-width:0">{{ $l }}</span></label>
                        @empty
                            <div class="pop-h">Nothing to filter by yet</div>
                        @endforelse
                    </div>
                </span>
            @endforeach
            <span class="sp"></span>{{ $tools ?? '' }}
            @if ($guide)
                <span x-data="pop({ w: 340 })" style="display:contents">
                    <button type="button" class="btn btn-g btn-sm" x-on:click="toggle($el)" aria-haspopup="true"><x-icon name="info" :size="14" /><span class="hide-md">Status guide</span></button>
                    <div class="pop" x-ref="p" x-show="open" x-cloak wire:ignore.self :style="pos"><div class="pop-h">What each status means</div>
                        @foreach ($guide as $st => [$gl, $gt])<div style="padding:7px 10px"><x-badge :status="$st" :label="$gl" /><div class="muted sm" style="margin-top:3px">{{ $gt }}</div></div>@endforeach
                    </div>
                </span>
            @endif
            @if ($cols)
                <span x-data="pop({ w: 230 })" style="display:contents">
                    <button type="button" class="icon-btn" x-on:click="toggle($el)" aria-label="Choose columns" title="Choose columns"><x-icon name="cols" :size="17" /></button>
                    <div class="pop" x-ref="p" x-show="open" x-cloak wire:ignore.self :style="pos">
                        <div class="pop-h">{{ __('Columns') }}</div>
                        @foreach ($cols as $k => $l)
                            <label class="pop-i" style="cursor:pointer" wire:key="col-{{ $k }}-{{ in_array($k, $lw->hide, true) ? 0 : 1 }}"><input type="checkbox" class="chk" wire:click="toggleCol('{{ $k }}')" @checked(! in_array($k, $lw->hide, true))><span style="flex:1">{{ $l }}</span></label>
                        @endforeach
                    </div>
                </span>
            @endif
            @if ($export)
                <span x-data="pop({ w: 200 })" style="display:contents">
                    <button type="button" class="icon-btn" x-on:click="toggle($el)" aria-label="Export" title="Export" aria-haspopup="true"><x-icon name="download" :size="17" /></button>
                    <div class="pop" x-ref="p" x-show="open" x-cloak wire:ignore.self :style="pos" role="menu">
                        <div class="pop-h">{{ __('Export what is shown') }}</div>
                        <button type="button" class="pop-i" wire:click="export('xlsx')" x-on:click="open = false"><x-icon name="download" />Excel (.xlsx)</button>
                        <button type="button" class="pop-i" wire:click="export('csv')" x-on:click="open = false"><x-icon name="file" />CSV</button>
                    </div>
                </span>
            @endif
        </div>
    </div>
    <div>
        @if ($active || trim($lw->q) !== '')
            <div class="dt-chips"><span class="muted sm"><b style="color:var(--text)">{{ $rows->total() }}</b> of {{ $all }} {{ $noun }}</span>
                @if (trim($lw->q) !== '')<button type="button" class="fchip" wire:click="$set('q', '')" aria-label="Remove search filter">Search: "{{ trim($lw->q) }}"<x-icon name="x" :size="12" /></button>@endif
                @foreach ($facets as $k => [$fl, $opts])
                    @foreach ((array) ($lw->f[$k] ?? []) as $v)
                        <button type="button" class="fchip" wire:click="dropFacet('{{ $k }}', '{{ $v }}')" aria-label="Remove filter {{ $fl }}: {{ $opts[$v] ?? $v }}"><span class="faint">{{ $fl }}:</span> {{ $opts[$v] ?? $v }}<x-icon name="x" :size="12" /></button>
                    @endforeach
                @endforeach
                <button type="button" class="link sm" wire:click="clearFilters">{{ __('Clear all') }}</button>
            </div>
        @endif
        @if ($all === 0 && isset($empty))
            {{ $empty }}
        @elseif ($rows->total() === 0)
            <x-empty icon="search" :title="'No ' . $noun . ' match'" text="Remove a filter or try a different search."><button type="button" class="btn" wire:click="clearFilters">Clear all filters</button></x-empty>
        @else
            <div class="tbl-wrap"><table class="tbl" wire:loading.class="busy"><thead><tr>
                @if ($keys !== null)<th class="hide-sm" style="width:40px"><input type="checkbox" class="chk" wire:click="togglePage({{ json_encode($pageKeys) }})" aria-label="Select all on this page" @checked($nsel && $nsel === count($pageKeys)) @if ($nsel && $nsel < count($pageKeys)) data-indet="1" @endif></th>@endif
                {{ $head }}
            </tr></thead><tbody>{{ $slot }}</tbody></table></div>
            <div class="dt-foot"><span>{{ $rows->firstItem() }}-{{ $rows->lastItem() }} of {{ $rows->total() }}@isset($foot) · {{ $foot }}@endisset</span><span class="sp"></span>
                <label class="row hide-sm" style="gap:8px">{{ __('Rows') }}<select class="select" aria-label="Rows per page" wire:model.live="per"><option>10</option><option>25</option><option>50</option></select></label>
                <nav class="pgn" aria-label="Pages"><button type="button" class="icon-btn" wire:click="previousPage" @disabled($rows->onFirstPage()) aria-label="Previous page"><x-icon name="left" :size="15" /></button>
                    @foreach ($pages($rows->currentPage(), $rows->lastPage()) as $p)
                        @if ($p === '…')<span class="faint">…</span>@else<button type="button" class="pg {{ $p === $rows->currentPage() ? 'on' : '' }}" @if ($p === $rows->currentPage()) aria-current="page" @endif wire:click="gotoPage({{ $p }})">{{ $p }}</button>@endif
                    @endforeach
                    <button type="button" class="icon-btn" wire:click="nextPage" @disabled(! $rows->hasMorePages()) aria-label="Next page"><x-icon name="right" :size="15" /></button></nav>
            </div>
            @if ($lw->selected && isset($bulk))
                <div class="bulkbar in" role="region" aria-label="Bulk actions"><span class="bb-n">{{ count($lw->selected) }}</span><span>selected</span><span class="bb-sep"></span>{{ $bulk }}<button type="button" class="bb-x" wire:click="$set('selected', [])" aria-label="Clear selection" title="Clear selection"><x-icon name="x" :size="15" /></button></div>
            @endif
        @endif
    </div>
</section>
