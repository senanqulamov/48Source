@props(['title' => null, 'sub' => null, 'flush' => false])
<section {{ $attributes->class('card') }}>
    @if ($title)
        <div class="card-h"><h2 class="card-t">{{ $title }}</h2>@if ($sub)<span class="card-s">{{ $sub }}</span>@endif<span class="sp"></span>{{ $act ?? '' }}</div>
    @endif
    @if ($flush){{ $slot }}@else<div class="card-b">{{ $slot }}</div>@endif
</section>
