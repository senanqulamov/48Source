<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', '48Source') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
    <tallstackui:script/>
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @if(request()->routeIs('login'))
        {{ $slot }}
    @else
        <div class="source-ui" data-theme="light">
            <main class="page" style="max-width:680px;margin:5vh auto">{{ $slot }}</main>
        </div>
    @endif
    @livewireScripts
</body>
</html>
