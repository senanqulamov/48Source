@props(['kind' => 'info'])
<div class="note {{ $kind }}"><x-icon :name="$kind === 'ok' ? 'check' : (in_array($kind, ['warn', 'bad']) ? 'alert' : 'info')" /><div>{{ $slot }}</div></div>
