<div>
    <x-ph title="Activity log" desc="Who did what and when. Every business action is recorded and cannot be edited." :crumbs="[['Activity log']]" />
    <x-dt :lw="$this" :rows="$rows" id="log" search="Search action, person or record" label="Search the log" noun="events" :all="$all" :facets="['area' => ['Area', $areas]]" :export="true">
        <x-slot:empty><x-empty icon="clip" title="Nothing recorded yet" text="Actions appear here as people work." /></x-slot:empty>
        <x-slot:head><x-th col="created_at" :sort="$sort ?: 'created_at'">When</x-th><x-th col="actor_name">User</x-th><x-th col="area" cls="hide-sm">Area</x-th><th>Event</th><th class="hide-sm">Record</th></x-slot:head>
        @foreach ($rows as $log)
            <tr wire:key="l-{{ $log->id }}"><td class="muted" style="white-space:nowrap" title="{{ $log->created_at }}">{{ $log->created_at->format('j M Y, H:i') }}</td><td>{{ $log->actor_name }}</td><td class="hide-sm"><span class="tag">{{ $log->area }}</span></td><td class="t1">{{ $log->action }}</td>
                <td class="hide-sm">@if ($log->url())<a class="link mono" href="{{ $log->url() }}" wire:navigate>{{ $log->subject_label }}</a>@else<span class="mono">{{ $log->subject_label ?? '-' }}</span>@endif</td></tr>
        @endforeach
    </x-dt>
</div>
