<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} · 48Source</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/extra.css') }}?v={{ filemtime(public_path('css/extra.css')) }}">
    <script src="{{ asset('js/ui.js') }}?v={{ filemtime(public_path('js/ui.js')) }}" defer data-navigate-once></script>
</head>
<body>
<div class="login">
    <main class="login-form" style="max-width:520px">
        <div class="row" style="margin-bottom:8px"><span class="mark" aria-hidden="true">48</span><span style="font-size:18px;font-weight:600;letter-spacing:-.01em">48Source</span></div>
        {{ $slot }}
    </main>
    <aside class="login-side">
        <div><h2>Every request, quotation and award on one accountable record.</h2></div>
        <div class="ledger" aria-hidden="true">
            <div><span>RFQ</span><b style="flex:1">Ask several suppliers for prices</b><span class="a">Compare</span></div>
            <div><span>Award</span><b style="flex:1">Choose with a recorded reason</b><span>Order</span></div>
            <div><span>PO</span><b style="flex:1">Follow it to delivery</b><span>Invoice</span></div>
        </div>
    </aside>
</div>
<div id="toasts" aria-live="polite" @if (session('toast')) data-flash="{{ session('toast') }}" @endif></div>
@foreach (['down', 'cal', 'check', 'left', 'right', 'alert'] as $i)<template id="ic-{{ $i }}"><x-icon :name="$i" /></template>@endforeach
</body>
</html>
