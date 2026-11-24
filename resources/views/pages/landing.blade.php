<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f3ee">
    <meta name="description" content="48Source is procurement software for buyers, suppliers and markets: requests for quotation, fair comparison, awards with a recorded reason, orders, receipts and invoices.">
    <title>48Source - procurement on one accountable record</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Onest:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #f5f3ee;
            --sheet: #fbfaf6;
            --ink: #12151c;
            --soft: #5f5e57;
            --line: #d9d5c8;
            --lime: #c4f53c;
            --lime-ink: #2c3a00;
            --display: 'Bricolage Grotesque', 'Onest', system-ui, sans-serif;
            --body: 'Onest', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --wrap: 1180px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: var(--paper); color: var(--ink); font: 400 17px/1.55 var(--body); -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        a:focus-visible, button:focus-visible { outline: 2px solid var(--ink); outline-offset: 3px; border-radius: 4px; }
        .wrap { max-width: var(--wrap); margin: 0 auto; padding: 0 28px; }
        .num { font-family: var(--display); font-variant-numeric: tabular-nums; font-weight: 500; color: var(--soft); letter-spacing: 0; }

        /* top */
        .top { position: sticky; top: 0; z-index: 20; background: color-mix(in srgb, var(--paper) 92%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
        .top .wrap { display: flex; align-items: center; gap: 28px; height: 68px; }
        .brand { display: flex; align-items: baseline; gap: 2px; font: 700 22px/1 var(--display); letter-spacing: -.02em; }
        .brand b { background: var(--ink); color: var(--lime); padding: 5px 7px 6px; border-radius: 7px; font-weight: 700; margin-right: 6px; }
        .menu { display: flex; gap: 26px; margin-left: 18px; flex: 1; }
        .menu a { display: flex; gap: 7px; align-items: baseline; font-size: 15px; color: var(--ink); padding: 6px 0; border-bottom: 1px solid transparent; }
        .menu a:hover { border-bottom-color: var(--ink); }
        .menu .num { font-size: 12px; }
        .langs { display: flex; gap: 2px; font-size: 13px; }
        .langs a { padding: 5px 7px; border-radius: 6px; color: var(--soft); }
        .langs a[aria-current="true"] { color: var(--ink); background: #e9e5d8; }
        .btn { display: inline-flex; align-items: center; justify-content: center; height: 46px; padding: 0 22px; border-radius: 999px; font: 500 16px/1 var(--body); border: 1px solid var(--ink); transition: background .15s, color .15s; white-space: nowrap; }
        .btn-ink { background: var(--ink); color: var(--paper); }
        .btn-ink:hover { background: #262b36; }
        .btn-line:hover { background: var(--ink); color: var(--paper); }
        .btn-lime { background: var(--lime); border-color: var(--lime); color: var(--ink); }
        .btn-lime:hover { background: #d3fb5e; }
        .btn-sm { height: 38px; padding: 0 16px; font-size: 14px; }

        /* hero */
        .hero { padding: 76px 0 84px; }
        .hero .wrap { display: grid; grid-template-columns: minmax(0, 1.02fr) minmax(0, .98fr); gap: 56px; align-items: center; }
        .count { display: inline-flex; gap: 10px; align-items: center; font-size: 14px; margin-bottom: 26px; }
        .count::after { content: ''; width: 46px; height: 1px; background: var(--ink); }
        h1 { font: 600 clamp(44px, 6.3vw, 86px)/.96 var(--display); letter-spacing: -.035em; margin: 0 0 26px; text-wrap: balance; }
        .lead { font-size: 19px; line-height: 1.55; color: var(--soft); max-width: 31em; margin: 0 0 34px; }
        .acts { display: flex; flex-wrap: wrap; gap: 12px; }
        .by { margin-top: 38px; font-size: 14px; color: var(--soft); }
        .by a { color: var(--ink); border-bottom: 1px solid var(--ink); }

        /* the sketch: a quotation comparison */
        .sketch { background: var(--sheet); border: 1px solid var(--ink); border-radius: 14px; box-shadow: 10px 10px 0 var(--ink); overflow: hidden; font-size: 14px; }
        .sk-h { display: flex; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--ink); font-family: var(--display); }
        .sk-h span { color: var(--soft); }
        .sk-t { width: 100%; border-collapse: collapse; }
        .sk-t th, .sk-t td { padding: 11px 14px; text-align: right; border-bottom: 1px solid var(--line); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sk-t th:first-child, .sk-t td:first-child { text-align: left; white-space: normal; padding-left: 18px; }
        .sk-t thead th { font-weight: 500; color: var(--soft); font-size: 13px; }
        .sk-t small { display: block; color: var(--soft); font-size: 12px; }
        .sk-t .low { position: relative; z-index: 0; font-weight: 600; }
        .sk-t .low::before { content: ''; position: absolute; z-index: -1; inset: 6px 6px 6px auto; width: calc(100% - 14px); background: var(--lime); border-radius: 6px; transform-origin: right center; animation: mark .5s cubic-bezier(.2, .8, .2, 1) both; }
        .sk-t tr:nth-child(1) .low::before { animation-delay: .35s; }
        .sk-t tr:nth-child(2) .low::before { animation-delay: .5s; }
        .sk-t tr:nth-child(3) .low::before { animation-delay: .65s; }
        .sk-t tfoot .low::before { animation-delay: .9s; }
        .sk-t tfoot td { border-bottom: 0; font-weight: 600; padding-top: 14px; padding-bottom: 14px; }
        .sk-f { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; background: var(--ink); color: var(--paper); }
        .sk-f span { color: #b9b7ad; font-size: 13px; }
        .sk-f b { font: 500 14px/1 var(--body); background: var(--lime); color: var(--ink); padding: 9px 14px; border-radius: 999px; }
        @keyframes mark { from { transform: scaleX(0); } to { transform: scaleX(1); } }

        /* sections */
        section { border-top: 1px solid var(--line); padding: 84px 0; }
        .sec-h { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 28px; margin-bottom: 52px; align-items: start; }
        .sec-h .num { font-size: 14px; padding-top: 14px; }
        h2 { font: 600 clamp(32px, 4.2vw, 56px)/1 var(--display); letter-spacing: -.03em; margin: 0 0 14px; }
        .sec-h p { margin: 0; color: var(--soft); max-width: 36em; }

        .flow { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); border-top: 1px solid var(--ink); }
        .flow li { padding: 20px 18px 4px 0; border-right: 1px solid var(--line); padding-left: 18px; }
        .flow li:first-child { padding-left: 0; }
        .flow li:last-child { border-right: 0; }
        .flow .num { font-size: 13px; display: block; margin-bottom: 30px; }
        .flow h3 { font: 600 23px/1.1 var(--display); letter-spacing: -.02em; margin: 0 0 8px; }
        .flow p { margin: 0; font-size: 15px; color: var(--soft); line-height: 1.5; }

        .index { border-top: 1px solid var(--ink); margin: 0; }
        .index div { display: grid; grid-template-columns: 200px minmax(0, 300px) minmax(0, 1fr); gap: 28px; padding: 17px 0; border-bottom: 1px solid var(--line); align-items: baseline; }
        .index dt { grid-column: 2; font: 600 20px/1.2 var(--display); letter-spacing: -.015em; }
        .index dd { grid-column: 3; margin: 0; color: var(--soft); font-size: 16px; }
        .index div:hover dt { text-decoration: underline; text-decoration-color: var(--lime); text-decoration-thickness: 4px; text-underline-offset: 3px; }

        .roles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-top: 1px solid var(--ink); }
        .roles article { padding: 24px 22px 6px; border-right: 1px solid var(--line); }
        .roles article:first-child { padding-left: 0; }
        .roles article:last-child { border-right: 0; }
        .roles h3 { font: 600 26px/1 var(--display); letter-spacing: -.02em; margin: 0 0 16px; }
        .roles ul { list-style: none; margin: 0; padding: 0; font-size: 15px; color: var(--soft); }
        .roles li { padding: 8px 0; border-top: 1px solid var(--line); }

        .market .wrap > div { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 56px; align-items: center; }
        .market .sec-h { display: block; margin: 0; }
        .market .sec-h .num { display: block; padding: 0 0 18px; }
        .basket { background: var(--sheet); border: 1px solid var(--ink); border-radius: 14px; padding: 6px 20px; font-size: 15px; }
        .basket div { display: flex; justify-content: space-between; gap: 14px; padding: 13px 0; border-bottom: 1px solid var(--line); font-variant-numeric: tabular-nums; }
        .basket div:last-child { border-bottom: 0; font-weight: 600; }
        .basket small { display: block; color: var(--soft); font-size: 13px; }

        .record { background: var(--ink); color: var(--paper); border-top: 0; }
        .record .num, .record .sec-h p { color: #b9b7ad; }
        .record h2 { max-width: 15em; }
        .facts { list-style: none; margin: 0 0 46px; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 56px; border-top: 1px solid #3a3f4b; margin-left: 228px; }
        .facts li { padding: 16px 0 16px 26px; border-bottom: 1px solid #3a3f4b; position: relative; color: #e7e4da; font-size: 16px; }
        .facts li::before { content: ''; position: absolute; left: 0; top: 25px; width: 12px; height: 6px; background: var(--lime); border-radius: 2px; }
        .record .acts { margin-left: 228px; }
        .record .btn-line { border-color: var(--paper); color: var(--paper); }
        .record .btn-line:hover { background: var(--paper); color: var(--ink); }

        footer { padding: 46px 0 56px; font-size: 14px; color: var(--soft); }
        footer .wrap { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 28px; }
        footer h4 { font: 500 13px/1 var(--display); color: var(--soft); margin: 0 0 14px; }
        footer a { display: block; color: var(--ink); padding: 4px 0; }
        footer a:hover { text-decoration: underline; }
        footer p { margin: 12px 0 0; max-width: 24em; }

        @media (max-width: 980px) {
            .menu { display: none; }
            .top .wrap { gap: 12px; }
            .langs { margin-left: auto; }
            .hero { padding: 48px 0 60px; }
            .hero .wrap, .market .wrap > div { grid-template-columns: 1fr; gap: 44px; }
            .sec-h { grid-template-columns: 1fr; gap: 6px; margin-bottom: 36px; }
            .sec-h .num { padding-top: 0; }
            .flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .flow li, .flow li:first-child { padding: 18px 14px 18px 0; border-right: 0; border-bottom: 1px solid var(--line); }
            .flow .num { margin-bottom: 12px; }
            .index div { grid-template-columns: 1fr; gap: 4px; }
            .index dt, .index dd { grid-column: 1; }
            .roles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .roles article, .roles article:first-child { padding: 22px 16px 14px 0; border-right: 0; }
            .facts { grid-template-columns: 1fr; margin-left: 0; }
            .record .acts { margin-left: 0; }
            section { padding: 60px 0; }
            footer .wrap { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 560px) {
            .wrap { padding: 0 18px; }
            .sketch { box-shadow: 6px 6px 0 var(--ink); font-size: 13px; }
            .sk-t th, .sk-t td { padding: 10px 8px; }
            .sk-t th:first-child, .sk-t td:first-child { padding-left: 12px; }
            .sk-t small { display: none; }
            .roles, .flow { grid-template-columns: 1fr; }
            .top .btn { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .sk-t .low::before { animation: none; }
        }
    </style>
</head>
<body>
@php($here = app()->getLocale())
<header class="top">
    <div class="wrap">
        <a class="brand" href="{{ route('landing') }}" aria-label="48Source home"><b>48</b>source</a>
        <nav class="menu" aria-label="Primary">
            <a href="#flow"><span class="num">01</span>flow</a>
            <a href="#modules"><span class="num">02</span>modules</a>
            <a href="#workspaces"><span class="num">03</span>workspaces</a>
            <a href="#marketplace"><span class="num">04</span>marketplace</a>
            <a href="#record"><span class="num">05</span>record</a>
        </nav>
        <div class="langs" aria-label="Language">
            @foreach (['en' => 'EN', 'az' => 'AZ', 'ru' => 'RU'] as $code => $short)
                <a href="{{ route('landing', ['lang' => $code]) }}" hreflang="{{ $code }}" aria-current="{{ $here === $code ? 'true' : 'false' }}"><span>{{ $short }}</span></a>
            @endforeach
        </div>
        <a class="btn btn-ink btn-sm" href="{{ route('login') }}">Sign in</a>
    </div>
</header>

<main>
    <div class="hero">
        <div class="wrap">
            <div>
                <div class="count num"><span>00 of 05</span></div>
                <h1>every purchase on one accountable record.</h1>
                <p class="lead">48Source takes a purchase from the first request to the paid invoice. Ask several suppliers for prices, compare them on the same terms, and award with a reason that stays on file.</p>
                <div class="acts">
                    <a class="btn btn-ink" href="{{ route('login') }}">Sign in to your workspace</a>
                    <a class="btn btn-line" href="{{ route('register') }}">Register as a supplier</a>
                </div>
                <p class="by"><span>Built by</span> <a href="https://48team.space" rel="noopener">48Team</a><span>, Baku.</span></p>
            </div>

            <div class="sketch" role="img" aria-label="Quotation comparison table">
                <div class="sk-h"><b>RFQ-0142</b><span>reinforcement steel, three quotations</span></div>
                <table class="sk-t">
                    <thead><tr><th>Item</th><th>supplier a</th><th>supplier b</th><th>supplier c</th></tr></thead>
                    <tbody>
                        <tr><td>rebar 16 mm<small>120 t</small></td><td>81 600</td><td class="low">79 200</td><td>83 400</td></tr>
                        <tr><td>rebar 25 mm<small>80 t</small></td><td class="low">55 200</td><td>56 800</td><td>57 600</td></tr>
                        <tr><td>tie wire<small>400 kg</small></td><td>1 120</td><td>1 080</td><td class="low">960</td></tr>
                    </tbody>
                    <tfoot><tr><td>Total</td><td>137 920</td><td class="low">137 080</td><td>141 960</td></tr></tfoot>
                </table>
                <div class="sk-f"><span>Lowest complete quotation is marked.</span><b>Award</b></div>
            </div>
        </div>
    </div>

    <section id="flow">
        <div class="wrap">
            <div class="sec-h"><span class="num">01 of 05</span><div><h2>six steps, one thread.</h2><p>Each step hands its numbers to the next one, so nothing is typed twice and nothing is lost between a quotation and the invoice that pays it.</p></div></div>
            <ol class="flow">
                <li><span class="num">01</span><h3>request</h3><p>List the items, set the deadline, choose who may quote.</p></li>
                <li><span class="num">02</span><h3>quote</h3><p>Suppliers price each line and can revise until quoting closes.</p></li>
                <li><span class="num">03</span><h3>compare</h3><p>Every quotation side by side, with a weighted score.</p></li>
                <li><span class="num">04</span><h3>award</h3><p>Choose a supplier, or split by item, and write down why.</p></li>
                <li><span class="num">05</span><h3>order</h3><p>The award becomes a purchase order you follow to delivery.</p></li>
                <li><span class="num">06</span><h3>pay</h3><p>The invoice is checked against the order and what arrived.</p></li>
            </ol>
        </div>
    </section>

    <section id="modules">
        <div class="wrap">
            <div class="sec-h"><span class="num">02 of 05</span><div><h2>what is inside.</h2><p>One application. The parts below share the same suppliers, items, prices and history.</p></div></div>
            <dl class="index">
                <div><dt>requests for quotation</dt><dd>Invited or public, with sealed bids, files, and questions answered for every supplier at once.</dd></div>
                <div><dt>negotiation rounds</dt><dd>A best-and-final round or a timed reverse auction where each supplier sees only its own rank.</dd></div>
                <div><dt>requisitions</dt><dd>Internal requests that pass an approval chain set by their estimated total.</dd></div>
                <div><dt>contracts</dt><dd>Framework agreements with fixed prices, a value ceiling and release orders placed against them.</dd></div>
                <div><dt>orders and receipts</dt><dd>Purchase orders from issue to delivery, with goods receipts recorded line by line.</dd></div>
                <div><dt>invoice matching</dt><dd>A three-way match of order, receipt and invoice. Differences need a recorded reason to approve.</dd></div>
                <div><dt>supplier 360</dt><dd>Documents with expiry dates, quarterly scorecards, risk, action plans and field assessments.</dd></div>
                <div><dt>reports and budgets</dt><dd>Committed spend by month, supplier and category, savings on awards and an accounting export.</dd></div>
            </dl>
        </div>
    </section>

    <section id="workspaces">
        <div class="wrap">
            <div class="sec-h"><span class="num">03 of 05</span><div><h2>a workspace for each side.</h2><p>Your role decides what you see after signing in. Permissions are checked on every action, not only in the menu.</p></div></div>
            <div class="roles">
                <article><h3>buyer</h3><ul><li>Create requests and invite suppliers</li><li>Compare, negotiate and award</li><li>Follow orders, receipts and invoices</li></ul></article>
                <article><h3>supplier</h3><ul><li>Accept or decline invitations</li><li>Send and revise quotations</li><li>Acknowledge orders and send invoices</li></ul></article>
                <article><h3>seller</h3><ul><li>Keep a price list with stock</li><li>Confirm marketplace orders</li><li>Hand picking and delivery to workers</li></ul></article>
                <article><h3>administrator</h3><ul><li>Users, roles and permissions</li><li>Categories, currencies and feature switches</li><li>Monitoring and the activity log</li></ul></article>
            </div>
        </div>
    </section>

    <section id="marketplace" class="market">
        <div class="wrap">
            <div>
                <div class="sec-h"><span class="num">04 of 05</span><h2>a marketplace for the small things.</h2><p>Stores in building markets publish their prices and stock. A supplier can source each item of a request from the store it chooses, with one basket and one order per store.</p></div>
                <div class="basket" role="img" aria-label="Basket">
                    <div><span>tie wire 1.6 mm<small>store one · 20 coils</small></span><span>480.00</span></div>
                    <div><span>cement, 25 kg bag<small>store two · 60 bags</small></span><span>402.00</span></div>
                    <div><span>safety sign set<small>store two · 4 sets</small></span><span>236.00</span></div>
                    <div><span>Two orders, one for each store</span><span>1 118.00</span></div>
                </div>
            </div>
        </div>
    </section>

    <section id="record" class="record">
        <div class="wrap">
            <div class="sec-h"><span class="num">05 of 05</span><div><h2>nothing is awarded without a name, a time and a reason.</h2><p>The rules below are enforced by the system itself.</p></div></div>
            <ul class="facts">
                <li>A supplier never sees another supplier's prices, files or messages.</li>
                <li>Awarding to anyone but the lowest price needs a written reason.</li>
                <li>Nobody approves their own requisition.</li>
                <li>The activity log records who did what. It cannot be edited.</li>
                <li>Amounts are stored as whole cents and are never converted between currencies.</li>
                <li>Uploaded files are kept on a private disk and are only served after an access check.</li>
            </ul>
            <div class="acts">
                <a class="btn btn-lime" href="{{ route('login') }}">Sign in</a>
                <a class="btn btn-line" href="{{ route('register') }}">Register as a supplier</a>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="wrap">
        <div><a class="brand" href="{{ route('landing') }}" aria-label="48Source home"><b>48</b>source</a><p>Procurement software for buyers, suppliers and markets.</p></div>
        <div><h4>product</h4><a href="#flow">Flow</a><a href="#modules">Modules</a><a href="#marketplace">Marketplace</a></div>
        <div><h4>account</h4><a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Register as a supplier</a></div>
        <div><h4>company</h4><a href="https://48team.space" rel="noopener">48Team</a><span>Baku, Azerbaijan</span></div>
    </div>
</footer>
</body>
</html>
