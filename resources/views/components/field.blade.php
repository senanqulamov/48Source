@props(['label', 'for' => null, 'name' => null, 'help' => null, 'opt' => false, 'full' => false])
<div @class(['field', 'full' => $full])>
    <label @if ($for) for="{{ $for }}" @endif>{{ $label }}@if ($opt) <span class="opt">optional</span>@endif</label>
    {{ $slot }}
    @if ($name && $errors->has($name))
        <span class="errt"><x-icon name="alert" :size="13" />{{ $errors->first($name) }}</span>
    @elseif ($help)
        <span class="help">{{ $help }}</span>
    @endif
</div>
