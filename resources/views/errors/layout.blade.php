<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · 48Source</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body style="display:grid;place-items:center;min-height:100vh;padding:24px">
    <div class="card" style="max-width:520px;width:100%">
        <div class="empty"><div class="ei" style="font-weight:700">@yield('code')</div><h3>@yield('title')</h3><p>@yield('message')</p>
            <a class="btn btn-p" href="{{ url('/') }}">Go to my workspace</a></div>
    </div>
</body>
</html>
