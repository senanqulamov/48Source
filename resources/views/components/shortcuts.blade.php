@props(['items'])
{{-- $items: list of [url, icon, title, text] --}}
<div class="shortcuts">
    @foreach ($items as [$url, $icon, $title, $text])
        <a class="sc" href="{{ $url }}" wire:navigate><span class="si"><x-icon :name="$icon" :size="18" /></span><span style="min-width:0"><b>{{ $title }}</b><span>{{ $text }}</span></span></a>
    @endforeach
</div>
