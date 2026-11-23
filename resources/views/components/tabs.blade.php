@props(['tabs', 'on', 'model' => 'tab'])
{{-- $tabs: key => label or [label, count] --}}
<div class="tabs" role="tablist">
    @foreach ($tabs as $k => $t)
        @php [$l, $n] = is_array($t) ? $t : [$t, null]; @endphp
        <button type="button" role="tab" aria-selected="{{ $on === $k ? 'true' : 'false' }}" class="tab {{ $on === $k ? 'on' : '' }}" wire:click="$set('{{ $model }}', '{{ $k }}')">{{ $l }}@if ($n !== null)<span class="n">{{ $n }}</span>@endif</button>
    @endforeach
</div>
