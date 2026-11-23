@props(['items'])
<div class="strip">
    @foreach ($items as $i)
        @if (! empty($i['href']))
            <a class="strip-i" href="{{ $i['href'] }}" wire:navigate><span class="strip-l">{{ $i['l'] }}</span><span class="strip-v">{{ $i['v'] }}</span><span class="strip-s {{ $i['c'] ?? '' }}">{{ $i['s'] ?? '' }}&nbsp;</span></a>
        @else
            <div class="strip-i"><span class="strip-l">{{ $i['l'] }}</span><span class="strip-v">{{ $i['v'] }}</span><span class="strip-s {{ $i['c'] ?? '' }}">{{ $i['s'] ?? '' }}&nbsp;</span></div>
        @endif
    @endforeach
</div>
