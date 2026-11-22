<div class="source-ui" x-data="{ open: @entangle('isOpen'), selectedIndex: @entangle('selectedIndex') }"
    x-on:open-search.window="open=true; $nextTick(() => $refs.searchInput?.focus())"
    x-on:keydown.window="
        if ((($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k')) {
            $event.preventDefault(); open = true; $nextTick(() => $refs.searchInput?.focus());
        } else if (open && $event.key === 'Escape') {
            $event.preventDefault(); open = false;
        } else if (open && $event.key === 'ArrowDown') {
            $event.preventDefault(); $wire.selectNext();
        } else if (open && $event.key === 'ArrowUp') {
            $event.preventDefault(); $wire.selectPrevious();
        } else if (open && $event.key === 'Enter') {
            $event.preventDefault(); $wire.selectCurrent();
        }
    ">
    <div class="scrim source-dialog" x-show="open" x-cloak
         x-on:click.self="open=false" role="presentation"
         style="z-index:3200">
        <section class="modal wide source-search-dialog" role="dialog" aria-modal="true" aria-labelledby="source-search-label">
            <div class="ov-h">
                <x-source.icon name="search" :size="18" />
                <h2 id="source-search-label">{{ __('Search or jump to a page') }}</h2>
                <span class="sp"></span>
                <button type="button" class="icon-btn" x-on:click="open=false" aria-label="{{ __('Close search') }}"><x-source.icon name="x" :size="17" /></button>
            </div>
            <div class="ov-b stack" style="gap:0;padding:0;min-height:180px">
                <div class="source-search-input-wrap">
                    <input class="input" x-ref="searchInput" wire:model.live.debounce.300ms="query"
                           type="search" autocomplete="off"
                           placeholder="{{ __('Search RFQs, products, orders, users…') }}"
                           aria-label="{{ __('Search all available records') }}"/>
                </div>
                <div class="source-search-results">
                    @if(mb_strlen($query) < 2)
                        <div class="empty">
                            <span class="ei"><x-source.icon name="search" :size="22" /></span>
                            <h3>{{ __('Search your workspace') }}</h3>
                            <p>{{ __('Type at least two characters to find records you can access.') }}</p>
                        </div>
                    @elseif(empty($results))
                        <div class="empty">
                            <span class="ei"><x-source.icon name="search" :size="22" /></span>
                            <h3>{{ __('No matching records') }}</h3>
                            <p>{{ __('Try another name, reference number or keyword.') }}</p>
                        </div>
                    @else
                        @php $itemIndex = 0; @endphp
                        @foreach($results as $category => $items)
                            <div class="source-search-group">
                                <div class="pop-h">{{ __($category) }}</div>
                                @foreach($items as $item)
                                    <a class="pop-i source-search-result {{ $itemIndex === $selectedIndex ? 'on' : '' }}"
                                        href="{{ $item['url'] }}" wire:navigate x-on:click="open=false"
                                        aria-label="{{ $item['title'] }}">
                                        <span class="source-search-result-icon"><x-source.icon name="file" :size="16" /></span>
                                        <span class="source-search-result-text"><strong>{{ $item['title'] }}</strong><small>{{ $item['subtitle'] }}</small></span>
                                        <span class="tag">{{ $item['type'] }}</span>
                                    </a>
                                    @php $itemIndex++; @endphp
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="ov-f source-search-footer">
                <span>{{ __('↑↓ Navigate') }}</span><span>{{ __('Enter Select') }}</span><span>{{ __('Esc Close') }}</span>
                <span class="sp"></span>
                <span>{{ collect($results)->flatten(1)->count() }} {{ __('results') }}</span>
            </div>
        </section>
    </div>
</div>
