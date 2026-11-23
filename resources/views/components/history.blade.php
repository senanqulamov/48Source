@props(['logs'])
@if ($logs->isEmpty())
    <x-empty icon="clock" title="No history yet" text="Actions on this record will appear here." />
@else
    <ol class="h-tl">
        @foreach ($logs as $log)
            <li><span class="h-ic"><x-icon name="clock" :size="14" /></span>
                <div class="h-b"><div class="h-t">{{ $log->action }}</div>
                    <div class="h-m"><span class="tag">{{ $log->area }}</span>
                        @if ($log->subject_label)@if ($log->url())<a class="link mono" href="{{ $log->url() }}" wire:navigate>{{ $log->subject_label }}</a>@else<span class="mono">{{ $log->subject_label }}</span>@endif @endif
                        <span>{{ $log->actor_name }}</span><span class="sp"></span><span title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</span></div></div></li>
        @endforeach
    </ol>
@endif
