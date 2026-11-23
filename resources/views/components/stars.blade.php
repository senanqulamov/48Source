@props(['tenths' => 0])
@php $full = (int) round($tenths / 10); @endphp
<span class="stars" role="img" aria-label="Rated {{ number_format($tenths / 10, 1) }} of 5">@for ($i = 1; $i <= 5; $i++)<x-icon name="star" :size="14" class="{{ $i <= $full ? 'on' : '' }}" />@endfor</span><span class="mono sm">{{ $tenths ? number_format($tenths / 10, 1) : 'n/a' }}</span>
