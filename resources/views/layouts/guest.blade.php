<!DOCTYPE html>
@php($approvedGuest = config('source48.redesign_enabled') && request()->routeIs('login'))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $approvedGuest ? '' : 'dark' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @if($approvedGuest)
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
        @endif
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="{{ $approvedGuest ? '' : 'font-sans text-dark-text antialiased bg-dark-bg' }}">
        @if($approvedGuest)
            {{ $slot }}
        @else

        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="w-full bg-dark-surface border border-dark-border shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>

        @endif

        @livewireScripts
    </body>
</html>
