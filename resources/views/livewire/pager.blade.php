@php $pages = function (int $cur, int $n) { $o = []; for ($i = 1; $i <= $n; $i++) { if ($i === 1 || $i === $n || abs($i - $cur) <= 1) { $o[] = $i; } elseif (end($o) !== '…') { $o[] = '…'; } } return $o; }; @endphp
@if ($paginator->total() > 0)
    <div class="dt-foot"><span>{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span><span class="sp"></span>
        <label class="row hide-sm" style="gap:8px">Rows<select class="select" aria-label="Rows per page" wire:model.live="per"><option>10</option><option>25</option><option>50</option></select></label>
        <nav class="pgn" aria-label="Pages"><button type="button" class="icon-btn" wire:click="previousPage" @disabled($paginator->onFirstPage()) aria-label="Previous page"><x-icon name="left" :size="15" /></button>
            @foreach ($pages($paginator->currentPage(), $paginator->lastPage()) as $p)
                @if ($p === '…')<span class="faint">…</span>@else<button type="button" class="pg {{ $p === $paginator->currentPage() ? 'on' : '' }}" @if ($p === $paginator->currentPage()) aria-current="page" @endif wire:click="gotoPage({{ $p }})">{{ $p }}</button>@endif
            @endforeach
            <button type="button" class="icon-btn" wire:click="nextPage" @disabled(! $paginator->hasMorePages()) aria-label="Next page"><x-icon name="right" :size="15" /></button></nav>
    </div>
@endif
