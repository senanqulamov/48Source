@props(['steps', 'at', 'label' => 'Progress', 'next' => ''])
{{-- $steps: list of labels; $at: index of the current step, count($steps) when everything is done --}}
<div class="life" aria-label="{{ $label }}">
    <ol>
        @foreach ($steps as $i => $s)
            @php $done = $at > $i || ($at === count($steps) - 1 && $i === $at && ($attributes->get('final') ?? false)); @endphp
            <li class="{{ $done ? 'done' : ($at === $i ? 'now' : '') }}"><span class="d">@if ($done)<x-icon name="check" :size="11" />@else{{ $i + 1 }}@endif</span><span>{{ $s }}</span></li>
        @endforeach
    </ol>
    <span class="nx">{{ $next }}</span>{{ $slot }}
</div>
