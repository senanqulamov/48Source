<div class="row" style="gap:6px">
    <button type="button" class="icon-btn" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage()) aria-label="Previous page"><x-icon name="left" /></button>
    <span class="mono sm">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    <button type="button" class="icon-btn" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages()) aria-label="Next page"><x-icon name="right" /></button>
</div>
