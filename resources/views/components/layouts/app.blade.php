@php
    $u = auth()->user();
    $nav = \App\Support\Nav::for($u);
    $unread = $u->unreadNotifications()->count();
    $current = request()->route()?->getName() ?? '';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ $u->theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Workspace' }} · 48Source</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/extra.css') }}?v={{ filemtime(public_path('css/extra.css')) }}">
    <script>window.I18N = @json(\App\Support\Translator::forScripts())</script>
    <script src="{{ asset('js/ui.js') }}?v={{ filemtime(public_path('js/ui.js')) }}" defer data-navigate-once></script>
</head>
<body>
<div class="app" x-data="{ nav: false, collapsed: localStorage.getItem('sb') === '1' }" :class="{ navopen: nav, collapsed: collapsed }">
    <nav class="sb" aria-label="Primary">
        <div class="sb-top"><a class="brand" href="{{ route('dashboard') }}" wire:navigate aria-label="48Source home"><span class="mark" aria-hidden="true">48</span><span class="brand-name">48Source</span></a></div>
        <div class="sb-nav">
            @foreach ($nav as [$group, $items])
                <div class="sb-group"><div class="sb-label">{{ $group }}</div>
                    @foreach ($items as $it)
                        @php $area = fn ($r) => str_starts_with($r, 'market.') ? implode('.', array_slice(explode('.', $r), 0, 2)) : \Illuminate\Support\Str::before($r, '.'); $cur = $area($current) === 'market.basket' ? 'market.shop' : $area($current); $on = $cur === $area($it['route']) && ($it['route'] !== 'quotes.invitations' || str_starts_with($current, 'quotes')); @endphp
                        <a @class(['sb-item', 'on' => $on]) href="{{ $it['url'] }}" wire:navigate @if ($on) aria-current="page" @endif title="{{ $it['label'] }}" data-tour="{{ $it['tip'] }}"><x-icon :name="$it['icon']" :size="17" /><span>{{ $it['label'] }}</span>@if ($it['count'])<span class="sb-count">{{ $it['count'] }}</span>@endif</a>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="sb-foot"><a @class(['sb-item', 'on' => $current === 'settings']) href="{{ route('settings') }}" wire:navigate title="Settings"><x-icon name="sliders" :size="17" /><span>{{ __('Settings') }}</span></a></div>
    </nav>
    <div class="sb-scrim" x-show="nav" x-on:click="nav = false" x-cloak></div>
    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn menu-btn" x-on:click="nav = true" aria-label="Open navigation"><x-icon name="menu" :size="18" /></button>
            <button type="button" class="icon-btn collapse-btn" x-on:click="collapsed = !collapsed; localStorage.setItem('sb', collapsed ? '1' : '0')" aria-label="Collapse sidebar"><x-icon name="panel" :size="18" /></button>
            <span class="portal hide-md" title="You are signed in as {{ $u->roleLabel() }}"><x-icon name="shield" :size="14" />{{ __(':role workspace', ['role' => __($u->roleLabel())]) }}</span>
            <form class="tb-search" role="search" action="{{ route('search') }}" method="get" x-data x-on:submit.prevent="Livewire.navigate('{{ route('search') }}?q=' + encodeURIComponent($refs.q.value))" x-on:keydown.window.ctrl.k.prevent="$refs.q.focus()">
                <x-icon name="search" /><input x-ref="q" id="gsearch" name="q" type="search" placeholder="{{ __('Search requests, orders, suppliers…') }}" aria-label="Global search" autocomplete="off" value="{{ request('q') }}"><kbd>Ctrl K</kbd>
            </form>
            <div class="tb-actions">
                @if ($u->isSourcing() && $u->allowed('rfqs', 'C'))
                    <a class="btn btn-sm btn-p" href="{{ route('rfqs.create') }}" wire:navigate style="margin-right:6px"><x-icon name="plus" :size="14" /><span class="hide-md">New RFQ</span></a>
                @endif
                <div class="acct-wrap hide-sm" x-data="pop({ inline: true })">
                    <button type="button" class="btn btn-sm btn-g" x-on:click="toggle($el)" aria-label="{{ __('Language') }}" :aria-expanded="open" title="{{ __('Language') }}"><x-icon name="globe" :size="15" />{{ strtoupper(app()->getLocale()) }}</button>
                    <div class="pop acct-pop" role="menu" x-show="open" x-cloak wire:ignore.self x-transition.opacity style="width:190px">
                        <div class="pop-h">{{ __('Language') }}</div>
                        @foreach (config('procurement.locales') as $code => $name)
                            <form method="post" action="{{ route('prefs.locale') }}">@csrf<input type="hidden" name="locale" value="{{ $code }}"><button class="pop-i {{ app()->getLocale() === $code ? 'on' : '' }}" type="submit"><span style="flex:1">{{ $name }}</span>@if (app()->getLocale() === $code)<span class="k"><x-icon name="check" :size="15" /></span>@endif</button></form>
                        @endforeach
                    </div>
                </div>
                <div class="acct-wrap hide-sm" x-data="pop({ inline: true })">
                    <button type="button" class="icon-btn" x-on:click="toggle($el)" aria-label="{{ __('Help') }}" :aria-expanded="open" title="{{ __('Help') }}"><x-icon name="info" :size="17" /></button>
                    <div class="pop acct-pop" role="menu" x-show="open" x-cloak x-transition.opacity style="width:270px">
                        <div class="pop-h">{{ __('Help') }}</div>
                        <button type="button" class="pop-i" x-on:click="open = false; ui48.tour()"><x-icon name="pin" /><span style="flex:1"><b style="display:block">{{ __('Take the tour') }}</b><span class="faint xs">{{ __('Visit every page of your workspace') }}</span></span></button>
                        <button type="button" class="pop-i" x-on:click="open = false; ui48.shortcuts()"><x-icon name="sliders" /><span style="flex:1">{{ __('Keyboard shortcuts') }}</span><span class="k"><kbd>?</kbd></span></button>
                        <a class="pop-i" href="{{ route('privacy') }}" wire:navigate><x-icon name="shield" />{{ __('Privacy and your data') }}</a>
                        @if ($u->allowed('system'))<a class="pop-i" href="{{ route('system.index') }}" wire:navigate><x-icon name="server" />{{ __('System status') }}</a>@endif
                        <hr><div class="pop-h" style="text-transform:none;font-weight:500">{{ __('Signed in as') }} {{ $u->email }}</div>
                    </div>
                </div>
                <button type="button" class="icon-btn hide-sm" x-data x-on:click="const t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = t; fetch('{{ route('prefs.theme') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' }, body: JSON.stringify({ theme: t }) })" aria-label="Switch between light and dark theme" title="Light / dark theme"><x-icon name="moon" :size="17" /></button>
                <a class="icon-btn" href="{{ route('notifications.index') }}" wire:navigate aria-label="Notifications, {{ $unread }} unread" title="Notifications"><x-icon name="bell" :size="17" />@if ($unread)<span class="dot"></span>@endif</a>
                <div class="acct-wrap" x-data="pop({ inline: true })">
                    <button type="button" class="acct" x-on:click="toggle($el)" aria-label="Account menu" :aria-expanded="open"><span class="avatar av{{ $u->id % 5 }}" aria-hidden="true">{{ $u->initials() }}</span><span class="hide-md b sm">{{ $u->name }}</span></button>
                    <div class="pop acct-pop" role="menu" x-show="open" x-cloak wire:ignore.self x-transition.opacity>
                        <div class="pop-h">{{ $u->email }}<br>{{ $u->supplier?->name ?? config('app.org', 'Buyer organisation') }}</div><hr>
                        <a class="pop-i" href="{{ route('settings') }}" wire:navigate><x-icon name="sliders" />{{ __('Settings') }}</a>
                        <form method="post" action="{{ route('logout') }}">@csrf<button class="pop-i" type="submit"><x-icon name="logout" />{{ __('Sign out') }}</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content" id="main" tabindex="-1"><div class="page enter">{{ $slot }}</div></main>
    </div>
</div>
<div id="tour-t" hidden data-next="{{ __('Next') }}" data-back="{{ __('Back') }}" data-finish="{{ __('Finish') }}" data-skip="{{ __('Skip the tour') }}" data-step="{{ __('Step :n of :total') }}" data-behind="{{ __('The page behind this card is the one being described.') }}"></div>
<template id="keys-t"><div class="modal" role="dialog" aria-modal="true" aria-label="{{ __('Keyboard shortcuts') }}"><div class="ov-h"><h2>{{ __('Keyboard shortcuts') }}</h2><span class="sp"></span><button type="button" class="icon-btn" data-x aria-label="Close"><x-icon name="x" /></button></div><div class="ov-b"><div class="keys">
    <kbd>Ctrl K</kbd><span>{{ __('Search everything') }}</span><kbd>/</kbd><span>{{ __('Search the table on this page') }}</span><kbd>Enter</kbd><span>{{ __('Open the selected row') }}</span><kbd>Esc</kbd><span>{{ __('Close a window, a menu or the tour') }}</span>
    <kbd>&larr; &rarr;</kbd><span>{{ __('Previous or next day in a calendar, previous or next step in the tour') }}</span><kbd>&uarr; &darr;</kbd><span>{{ __('Move in a list of options, change the time') }}</span><kbd>Ctrl Enter</kbd><span>{{ __('Send a message') }}</span><kbd>?</kbd><span>{{ __('Show this list') }}</span>
</div></div></div></template>
<div id="toasts" aria-live="polite" @if (session('toast')) data-flash="{{ session('toast') }}" @endif></div>
@foreach (['down', 'cal', 'check', 'left', 'right', 'alert'] as $i)<template id="ic-{{ $i }}"><x-icon :name="$i" /></template>@endforeach
</body>
</html>
