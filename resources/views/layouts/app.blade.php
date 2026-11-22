<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', '48Source') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('48source-theme') === 'dark' ? 'dark' : 'light';
                document.documentElement.dataset.theme = theme;
                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.style.colorScheme = theme;
            } catch (error) { /* Private browsing: use light theme. */ }
        })();
    </script>
    <tallstackui:script/>
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-cloak>
    <x-dialog/>
    <x-toast/>
@php
    // Prefer the current route prefix to keep UI theme stable.
    if (request()->routeIs('buyer.*')) {
        $currentRole = 'buyer';
    } elseif (request()->routeIs('seller.*')) {
        $currentRole = 'seller';
    } elseif (request()->routeIs('supplier.field.*')) {
        $currentRole = 'supplier_worker';
    } elseif (request()->routeIs('supplier.*')) {
        $currentRole = 'supplier';
    } else {
        // Admin/default area.
        $currentRole = 'admin';
    }

    // If we're not in a role-prefixed area, fall back to the authenticated user's pivot roles.
    if ($currentRole === 'admin' && auth()->check()) {
        $roleNames = auth()->user()->roles()->pluck('name')->all();

        if (in_array('admin', $roleNames, true) || auth()->user()->isAdmin()) {
            $currentRole = 'admin';
        } elseif (in_array('seller', $roleNames, true) || auth()->user()->isSeller()) {
            $currentRole = 'seller';
        } elseif (in_array('supplier', $roleNames, true) || auth()->user()->isSupplier()) {
            $currentRole = 'supplier';
        } elseif (in_array('supplier_worker', $roleNames, true) || auth()->user()->isSupplierWorker()) {
            $currentRole = 'supplier_worker';
        } elseif (in_array('buyer', $roleNames, true) || auth()->user()->isBuyer()) {
            $currentRole = 'buyer';
        } else {
            $currentRole = auth()->user()->role ?? 'admin';
        }
    }
@endphp
    <x-layout.source-shell :role="$currentRole">{{ $slot }}</x-layout.source-shell>
    @auth
        @livewire('search.global-search')
    @endauth
    @livewireScripts
    @stack('scripts')
</body>
</html>
