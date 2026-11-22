<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $statusCode }} — {{ $title }} | {{ config('app.name','48Source') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="source-ui" data-theme="light" style="min-height:100dvh;display:grid;place-items:center;padding:24px;background:var(--bg);color:var(--text)">
        <section class="card" style="width:min(550px,100%);overflow:hidden">
            <div class="card-h"><a href="{{ url('/') }}" class="row link" style="gap:10px;text-decoration:none">
                <span class="mark" aria-hidden="true">48</span><strong>48Source</strong>
            </a><span class="sp"></span><span class="badge">{{ __('Status') }} {{ $statusCode }}</span></div>
            <div class="card-b stack" style="padding:28px">
                <div class="mono" style="font-size:54px;font-weight:800;line-height:1;color:var(--primary)">{{ $statusCode }}</div>
                <h1 class="ph-t">{{ $title }}</h1>
                <p class="ph-d">{{ $description }}</p>
                <div class="row wrap" style="margin-top:10px">
                    <a href="{{ url('/') }}" class="btn btn-p">{{ __('Go to home') }}</a>
                    @auth
                        @if(\Illuminate\Support\Facades\Route::has('dashboard'))
                            <a href="{{ route('dashboard') }}" class="btn">{{ __('Dashboard') }}</a>
                        @endif
                    @else
                        @if(\Illuminate\Support\Facades\Route::has('login'))
                            <a href="{{ route('login') }}" class="btn">{{ __('Sign in') }}</a>
                        @endif
                    @endauth
                </div>
            </div>
        </section>
    </main>
</body>
</html>