@props(['items'])
{{-- $items: list of [label, value, extra text, mono?] --}}
<div class="facts">
    @foreach ($items as $f)
        <div class="fact"><span>{{ $f[0] }}</span><b @class(['mono' => $f[3] ?? false])>{{ $f[1] }}</b>@if (! empty($f[2])) <span style="display:inline">{{ $f[2] }}</span>@endif</div>
    @endforeach
</div>
