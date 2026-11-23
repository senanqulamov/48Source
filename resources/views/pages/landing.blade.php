<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>48Source · Procurement for buyers, suppliers and markets</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}"><link rel="stylesheet" href="{{ asset('css/extra.css') }}">
    <style>body{background:var(--bg)}.lp{max-width:1080px;margin:0 auto;padding:28px 22px 60px}.lp-top{display:flex;align-items:center;gap:12px;margin-bottom:64px}.lp h1{font:800 clamp(34px,6vw,60px)/1.04 var(--display);letter-spacing:-.02em;max-width:16ch;margin-bottom:16px}.lp-lead{font-size:18px;color:var(--text-2);max-width:56ch;margin-bottom:26px}.lp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-top:56px}.lp-step{font:700 13px var(--mono);color:var(--text-3)}</style>
</head>
<body>
<main class="lp">
    <div class="lp-top"><span class="mark" aria-hidden="true">48</span><b style="font:700 20px var(--display)">48Source</b><span class="sp"></span><a class="btn" href="{{ route('login') }}">Sign in</a>@if (\App\Models\FeatureFlag::isOn('supplier_registration'))<a class="btn btn-p" href="{{ route('register') }}">Register as a supplier</a>@endif</div>
    <h1>Ask for prices, compare them fairly, and follow every order to payment.</h1>
    <p class="lead lp-lead">48Source connects buyers, suppliers and market stores in one place: requests for quotation, side-by-side comparison, purchase orders, receipts, invoices and a marketplace for the goods behind them.</p>
    <div class="row wrap"><a class="btn btn-p" href="{{ route('login') }}" style="height:46px;padding:0 22px">Sign in to your workspace</a></div>
    <div class="lp-grid">
        @foreach ([['Request', 'Describe what you need, list the items and invite suppliers. Sealed bids, questions and answers, best-and-final rounds and reverse auctions are built in.'], ['Compare and award', 'Every quotation side by side with a weighted score. Awarding to anyone but the lowest price needs a written reason.'], ['Order to payment', 'Receipts line by line and a three-way match that checks each invoice against the order and what arrived.'], ['Marketplace', 'Suppliers source each item from the store they choose. One basket, one order per store.']] as $i => [$t, $d])
            <section class="card" style="padding:18px"><div class="lp-step">{{ $i + 1 }}</div><h2 style="font:700 18px var(--display);margin:4px 0 6px">{{ $t }}</h2><p class="muted">{{ $d }}</p></section>
        @endforeach
    </div>
</main>
</body>
</html>
