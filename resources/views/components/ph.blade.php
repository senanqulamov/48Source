@props(['title', 'desc' => null, 'crumbs' => [], 'back' => null, 'pager' => null, 'recent' => null, 'recentSub' => ''])
@php
    $parent = $back ?? (count($crumbs) > 1 && ! empty($crumbs[count($crumbs) - 2][1]) ? $crumbs[count($crumbs) - 2] : [__('Home'), route('dashboard')]);
    $group = \App\Support\Nav::groupOf(auth()->user(), request()->route()?->getName() ?? '');
@endphp
@if ($crumbs)
    <div class="trail">
        <a class="tr-back" href="{{ $parent[1] }}" wire:navigate title="Back to {{ $parent[0] }}"><x-icon name="left" :size="15" /><span>{{ $parent[0] }}</span></a>
        <nav class="tr-path" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" wire:navigate class="tr-home" aria-label="Home" title="Home"><x-icon name="dash" :size="15" /></a>
            @if ($group && $group !== ($crumbs[0][0] ?? null))<x-icon name="right" :size="12" /><span class="tr-grp">{{ $group }}</span>@endif
            @foreach ($crumbs as $c)
                <x-icon name="right" :size="12" />
                @if ($loop->last)<b aria-current="page" title="{{ $c[0] }}">{{ $c[0] }}</b>@elseif (! empty($c[1]))<a href="{{ $c[1] }}" wire:navigate>{{ $c[0] }}</a>@else<span>{{ $c[0] }}</span>@endif
            @endforeach
        </nav>
        <div class="tr-tools">
            @if ($pager && $pager['of'] > 1)
                <span class="tr-pager">
                    @if ($pager['prev'])<a class="icon-btn" href="{{ $pager['prev'] }}" wire:navigate aria-label="Previous record" title="Previous"><x-icon name="left" :size="15" /></a>@else<button type="button" class="icon-btn" disabled aria-label="Previous record"><x-icon name="left" :size="15" /></button>@endif
                    <span class="mono">{{ $pager['n'] }} of {{ $pager['of'] }}</span>
                    @if ($pager['next'])<a class="icon-btn" href="{{ $pager['next'] }}" wire:navigate aria-label="Next record" title="Next"><x-icon name="right" :size="15" /></a>@else<button type="button" class="icon-btn" disabled aria-label="Next record"><x-icon name="right" :size="15" /></button>@endif
                </span>
            @endif
            <button type="button" class="btn btn-sm btn-g" data-recent-btn hidden><x-icon name="clock" :size="14" />{{ __('Recent') }}</button>
        </div>
    </div>
@endif
<header class="ph" @if ($recent) data-recent="{{ $recent }}" data-recent-sub="{{ $recentSub }}" @endif>
    <div style="min-width:0">{{ $pre ?? '' }}<h1 class="ph-t">{{ $title }}</h1>@if ($desc)<p class="ph-d">{{ $desc }}</p>@endif</div>
    @if (trim($slot))<div class="ph-a">{{ $slot }}</div>@endif
</header>
