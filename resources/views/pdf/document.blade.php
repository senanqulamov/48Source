<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $doc['number'] }}</title>
<style>
    @page { margin: 34px 38px 46px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #12151C; line-height: 1.45; }
    .top { width: 100%; margin-bottom: 18px; }
    .mark { display: inline-block; background: #C4F53C; color: #12151C; font-weight: bold; font-size: 15px; padding: 5px 8px; border-radius: 7px; }
    .brand { font-size: 15px; font-weight: bold; padding-left: 6px; }
    h1 { font-size: 20px; margin: 0; }
    .muted { color: #5B6373; }
    .pill { display: inline-block; border: 1px solid #C9CEDA; border-radius: 10px; padding: 1px 8px; font-size: 9.5px; }
    table { width: 100%; border-collapse: collapse; }
    .box td { vertical-align: top; width: 50%; padding: 10px 12px; border: 1px solid #DDE1EA; }
    .lbl { font-size: 9px; color: #5B6373; text-transform: uppercase; letter-spacing: .4px; }
    .facts td { padding: 4px 0; border-bottom: 1px solid #EEF0F5; }
    .facts td.k { color: #5B6373; width: 32%; }
    .lines th { text-align: left; font-size: 9.5px; color: #5B6373; border-bottom: 1.5px solid #12151C; padding: 6px 6px; }
    .lines td { padding: 6px 6px; border-bottom: 1px solid #E4E7EE; }
    .num { text-align: right; white-space: nowrap; }
    .total td { border-bottom: 0; border-top: 1.5px solid #12151C; font-weight: bold; font-size: 12px; padding-top: 8px; }
    .foot { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 8.5px; color: #8089A0; }
</style>
</head>
<body>
<div class="foot">{{ $doc['number'] }} · printed {{ $doc['printed'] }} · 48Source</div>
<table class="top"><tr>
    <td><span class="mark">48</span><span class="brand">{{ $doc['org'] }}</span></td>
    <td style="text-align:right"><h1>{{ $doc['title'] }}</h1><div style="font-size:12px">{{ $doc['number'] }} &nbsp;<span class="pill">{{ $doc['status'] }}</span></div></td>
</tr></table>
@if (! empty($doc['subject']))<p style="font-size:13px;font-weight:bold;margin:0 0 12px">{{ $doc['subject'] }}</p>@endif
<table class="box" style="margin-bottom:14px"><tr>
    @foreach (['left', 'right'] as $side)
        <td><div class="lbl">{{ $doc[$side][0] }}</div>@foreach (array_filter($doc[$side][1]) as $i => $line)<div @if ($i === 0) style="font-weight:bold;font-size:12px" @endif>{{ $line }}</div>@endforeach</td>
    @endforeach
</tr></table>
<table class="facts" style="margin-bottom:16px">@foreach ($doc['facts'] as $k => $v)<tr><td class="k">{{ $k }}</td><td>{{ $v }}</td></tr>@endforeach</table>
@foreach ($doc['text'] ?? [] as $k => $v)<div class="lbl">{{ $k }}</div><p style="margin:2px 0 12px;white-space:pre-line">{{ $v }}</p>@endforeach
@php $n = count($doc['head']); $numeric = fn ($i) => $i >= max(1, $n - 3) && $doc['head'][$i] !== 'Unit' && $doc['head'][$i] !== 'SKU' && $doc['head'][$i] !== 'Item'; @endphp
<table class="lines">
    <thead><tr>@foreach ($doc['head'] as $i => $h)<th @class(['num' => $numeric($i)])>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
        @foreach ($doc['rows'] as $row)<tr>@foreach ($row as $i => $cell)<td @class(['num' => $numeric($i)])>{{ $cell }}</td>@endforeach</tr>@endforeach
        @isset($doc['total'])<tr class="total"><td colspan="{{ $n - 1 }}">{{ $doc['total'][0] }}</td><td class="num">{{ $doc['total'][1] }}</td></tr>@endisset
    </tbody>
</table>
</body>
</html>
