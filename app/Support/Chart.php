<?php

namespace App\Support;

/** Server-rendered charts, drawn as SVG and HTML with the same markup the design uses. No JavaScript needed. */
final class Chart
{
    public const COLORS = ['var(--primary)', 'var(--brand)', 'var(--ok)', 'var(--warn)', 'var(--bad)', 'var(--text-3)', 'var(--info)'];

    private static function e(string|int $v): string
    {
        return e((string) $v);
    }

    /** @param array<int, array{0:string,1:int,2:string}> $segs label, count, colour */
    public static function donut(array $segs, string|int $center, string $sub): string
    {
        $tot = max(1, array_sum(array_column($segs, 1)));
        $r = 52;
        $c = 2 * M_PI * $r;
        $off = 0.0;
        $arcs = '';
        foreach ($segs as [$label, $n, $color]) {
            if (! $n) {
                continue;
            }
            $len = $n / $tot * $c;
            $arcs .= sprintf('<circle class="dn-a" r="%d" cx="70" cy="70" fill="none" stroke="%s" stroke-width="16" stroke-dasharray="%.2F %.2F" stroke-dashoffset="%.2F"><title>%s: %d (%d%%)</title></circle>', $r, $color, max(0, $len - 2.5), $c, -$off, self::e($label), $n, round($n / $tot * 100));
            $off += $len;
        }
        $legend = '';
        foreach ($segs as [$label, $n, $color]) {
            $legend .= sprintf('<li><i style="background:%s"></i><span class="grow">%s</span><b class="mono">%d</b><span class="faint mono" style="width:38px;text-align:right">%d%%</span></li>', $color, self::e($label), $n, round($n / $tot * 100));
        }

        return sprintf('<div class="viz-dn"><svg viewBox="0 0 140 140" role="img" aria-label="%s"><circle r="%d" cx="70" cy="70" fill="none" stroke="var(--sunken)" stroke-width="16"/><g transform="rotate(-90 70 70)">%s</g><text x="70" y="68" text-anchor="middle" class="dn-v">%s</text><text x="70" y="86" text-anchor="middle" class="dn-s">%s</text></svg><ul class="viz-lg">%s</ul></div>', self::e($sub), $r, $arcs, self::e($center), self::e($sub), $legend);
    }

    /**
     * Smooth area chart. Values are whole units (for money pass whole currency units, not cents).
     *
     * @param  array<int, string>  $labels
     * @param  array<int, int>  $v
     */
    public static function area(array $labels, array $v, string $unit = ''): string
    {
        $w = 560; $h = 190; $pl = 40; $pr = 16; $pt = 18; $pb = 26;
        $n = count($v);
        if ($n === 0) {
            return '<p class="faint">Nothing to show yet.</p>';
        }
        $hi = max(1, max($v)) * 1.15;
        $x = fn (int $i) => $pl + ($w - $pl - $pr) * ($n === 1 ? .5 : $i / ($n - 1));
        $y = fn (float $q) => $pt + ($h - $pt - $pb) * (1 - $q / $hi);
        $f = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        $short = fn (float $q) => $q >= 1000000 ? $f(round($q / 1000000, 1)) . 'M' : ($q >= 1000 ? ($q >= 10000 ? round($q / 1000) : $f(round($q / 1000, 1))) . 'k' : (string) round($q));
        $pts = [];
        foreach ($v as $i => $q) {
            $pts[] = [$x($i), $y($q)];
        }
        $line = '';
        foreach ($pts as $i => $p) {
            if ($i === 0) {
                $line .= "M{$f($p[0])},{$f($p[1])}";

                continue;
            }
            $a = $pts[$i - 1];
            $mx = $f(($a[0] + $p[0]) / 2);
            $line .= " C{$mx},{$f($a[1])} {$mx},{$f($p[1])} {$f($p[0])},{$f($p[1])}";
        }
        $grid = '';
        for ($i = 0; $i <= 3; $i++) {
            $q = $hi / 3 * $i;
            $grid .= sprintf('<line class="gl" x1="%d" x2="%d" y1="%s" y2="%s"/><text x="%d" y="%s" text-anchor="end">%s%s</text>', $pl, $w - $pr, $f($y($q)), $f($y($q)), $pl - 8, $f($y($q) + 3), $unit, $short($q));
        }
        $id = 'ag' . substr(md5(implode('', $labels) . implode('', $v)), 0, 8);
        $bw = ($w - $pl - $pr) / $n;
        $marks = '';
        foreach ($pts as $i => $p) {
            $val = $unit . number_format($v[$i]);
            $marks .= sprintf('<g class="ar-p"><rect x="%s" y="%d" width="%s" height="%d" fill="transparent"><title>%s: %s</title></rect><line x1="%s" x2="%s" y1="%d" y2="%d"/><circle cx="%s" cy="%s" r="4"/><text class="ar-t" x="%s" y="%s" text-anchor="middle">%s</text></g><text x="%s" y="%d" text-anchor="middle">%s</text>',
                $f($p[0] - $bw / 2), $pt, $f($bw), $h - $pt - $pb, self::e($labels[$i]), $val, $f($p[0]), $f($p[0]), $pt, $h - $pb, $f($p[0]), $f($p[1]), $f(min($w - 40, max($pl + 30, $p[0]))), $f(max(12, $p[1] - 10)), $val, $f($p[0]), $h - 8, self::e($labels[$i]));
        }

        return sprintf('<svg class="chart viz-ar" viewBox="0 0 %d %d" role="img" aria-label="Chart by month"><defs><linearGradient id="%s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--primary)" stop-opacity=".28"/><stop offset="1" stop-color="var(--primary)" stop-opacity="0"/></linearGradient></defs>%s<path d="%s L%s,%d L%s,%d Z" fill="url(#%s)"/><path class="ar-l" d="%s" fill="none" pathLength="1"/>%s</svg>',
            $w, $h, $id, $grid, $line, $f($x($n - 1)), $h - $pb, $f($x(0)), $h - $pb, $id, $line, $marks);
    }

    /**
     * Vertical bars, one or two series, as in the accepted design.
     *
     * @param array<int, string> $labels
     * @param array<int, int|float> $a
     * @param array<int, int|float>|null $b
     */
    public static function bars(array $labels, array $a, ?array $b = null, string $unit = ''): string
    {
        $n = count($labels);
        if ($n === 0) {
            return '<p class="faint">Nothing to show yet.</p>';
        }
        $w = 560; $h = 190; $pl = 34; $pb = 22; $pt = 10;
        $top = max(1, max(array_merge($a, $b ?? [0])));
        $step = 10 ** max(0, (int) floor(log10($top)) - 1) * 5;
        $max = (int) (ceil($top / $step) * $step) ?: 5;
        $gw = ($w - $pl) / $n;
        $bw = min(16, $gw / 3);
        $y = fn (float $v) => round($pt + ($h - $pt - $pb) * (1 - $v / $max), 1);
        $g = '';
        for ($i = 0; $i <= 4; $i++) {
            $v = $max / 4 * $i;
            $g .= sprintf('<line class="gl" x1="%d" x2="%d" y1="%s" y2="%s"/><text x="%d" y="%s" text-anchor="end">%s</text>', $pl, $w, $y($v), $y($v), $pl - 6, $y($v) + 3, number_format($v));
        }
        foreach (array_values($labels) as $i => $l) {
            $cx = $pl + $gw * $i + $gw / 2;
            $va = (float) ($a[$i] ?? 0);
            $g .= sprintf('<rect class="a" x="%s" y="%s" width="%s" height="%s" rx="2"><title>%s: %s%s</title></rect>', round($cx - ($b ? $bw + 1 : $bw / 2), 1), $y($va), round($bw, 1), max(0, round($h - $pb - $y($va), 1)), self::e($l), $unit, number_format($va));
            if ($b) {
                $vb = (float) ($b[$i] ?? 0);
                $g .= sprintf('<rect class="bser" x="%s" y="%s" width="%s" height="%s" rx="2"><title>%s: %s%s</title></rect>', round($cx + 1, 1), $y($vb), round($bw, 1), max(0, round($h - $pb - $y($vb), 1)), self::e($l), $unit, number_format($vb));
            }
            $g .= sprintf('<text x="%s" y="%d" text-anchor="middle">%s</text>', round($cx, 1), $h - 6, self::e($l));
        }

        return sprintf('<svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="Bar chart">%s</svg>', $w, $h, $g);
    }

    /**
     * Horizontal bars: label, bar scaled to the largest value, value text.
     *
     * @param array<int, array{0:string,1:int|float,2:string}> $rows
     */
    public static function hbars(array $rows, string $empty = 'Nothing to show yet.'): string
    {
        if (! $rows) {
            return '<p class="faint">'.self::e($empty).'</p>';
        }
        $max = max(1, max(array_column($rows, 1)));
        $out = '';
        foreach ($rows as [$label, $value, $text]) {
            $out .= sprintf('<div class="hb"><span class="sm">%s</span><div class="prog"><i style="width:%s%%"></i></div><span class="mono sm num">%s</span></div>', self::e($label), max(0, min(100, round($value * 100 / $max, 1))), self::e($text));
        }

        return $out;
    }

    /** The coloured banner of a market card: a gradient and a row of stalls, always the same for the same market. */
    public static function marketArt(int $id): string
    {
        $h = ($id * 47 + 4) % 360;
        $bars = '';
        for ($i = 0; $i < 9; $i++) {
            $seed = crc32($id.'-'.$i);
            $bars .= sprintf('<rect x="%d" y="%s" width="34" height="90" rx="5" fill="rgba(255,255,255,%s)"/>', 6 + $i * 46, 30 + ($seed % 27), round(0.08 + (($seed >> 5) % 11) / 100, 2));
        }

        return sprintf('<svg class="m-art" viewBox="0 0 400 120" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><defs><linearGradient id="mg%d" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(%d 65%% 46%%)"/><stop offset="1" stop-color="hsl(%d 70%% 32%%)"/></linearGradient></defs><rect width="400" height="120" fill="url(#mg%d)"/>%s</svg>', $id, $h, ($h + 50) % 360, $id, $bars);
    }

    /** @param array<int, array{0:string,1:int}> $steps label, count */
    public static function funnel(array $steps): string
    {
        $mx = max(1, max(array_column($steps, 1) ?: [1]));
        $out = '';
        foreach ($steps as $i => [$label, $n]) {
            $prev = $i ? $steps[$i - 1][1] : 0;
            $out .= sprintf('<div class="fn-r"><span class="fn-l">%s</span><div class="fn-b"><i style="width:%s%%;animation-delay:%dms"></i></div><b class="mono fn-v">%d</b><span class="fn-c faint sm">%s</span></div>', self::e($label), round(max(4, $n / $mx * 100), 1), $i * 70, $n, $i && $prev ? round($n / $prev * 100) . '%' : '');
        }

        return '<div class="viz-fn">' . $out . '</div>';
    }

    /** @param array<int, array{0:string,1:int,2:string}> $rows label, value used for the bar, text shown */
    public static function rank(array $rows): string
    {
        if (! $rows) {
            return '<p class="faint">Nothing to show yet.</p>';
        }
        $mx = max(1, max(array_column($rows, 1)));
        $out = '';
        foreach (array_values($rows) as $i => [$label, $n, $text]) {
            $out .= sprintf('<div class="rk-r"><span class="rk-n mono">%d</span><div class="grow" style="min-width:0"><div class="row"><span class="rk-t">%s</span><span class="sp"></span><b class="mono">%s</b></div><div class="rk-b"><i style="width:%s%%;animation-delay:%dms"></i></div></div></div>', $i + 1, self::e($label), self::e($text), round($n / $mx * 100, 1), $i * 60);
        }

        return '<div class="viz-rk">' . $out . '</div>';
    }

    /** Keys (Y-m) and short labels of the last six months, oldest first. @return array<string, string> */
    public static function lastMonths(int $n = 6): array
    {
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $out[$m->format('Y-m')] = $m->format('M');
        }

        return $out;
    }

    /** A generated picture for a product, steady for the same SKU, coloured by category. */
    public static function productArt(string $sku, string $category, bool $big = false): string
    {
        $r = fn (string $k) => (crc32($k) % 1000) / 1000;
        $h = (int) round($r($category) * 360);
        [$a, $b, $c] = [$r($sku), $r($sku . 'b'), $r($sku . 'c')];
        $ini = mb_strtoupper(mb_substr(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), preg_split('/[ &]+/', $category, -1, PREG_SPLIT_NO_EMPTY))), 0, 2));
        $f = fn (float $n) => rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
        $shape = match ((int) floor($a * 3)) {
            0 => sprintf('<circle cx="%s" cy="%s" r="%s" fill="hsl(%d 70%% 62%% / .55)"/><circle cx="%s" cy="%s" r="%s" fill="hsl(%d 75%% 55%% / .5)"/>', $f(40 + $b * 80), $f(30 + $c * 40), $f(34 + $a * 22), $h, $f(150 - $b * 50), $f(90 - $c * 30), $f(22 + $c * 18), ($h + 40) % 360),
            1 => sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="16" transform="rotate(%s 100 60)" fill="hsl(%d 70%% 60%% / .55)"/><rect x="%s" y="50" width="60" height="60" rx="12" transform="rotate(%s 140 80)" fill="hsl(%d 70%% 52%% / .45)"/>', $f(20 + $b * 40), $f(18 + $c * 20), $f(70 + $a * 40), $f(70 + $a * 30), $f(-18 + $b * 36), $h, $f(110 - $c * 30), $f(12 + $c * 20), ($h + 320) % 360),
            default => implode('', array_map(fn ($i) => sprintf('<rect x="%d" y="-20" width="20" height="180" transform="rotate(%s 100 60)" fill="hsl(%d 70%% %d%% / .5)"/>', -20 + $i * 42, $f(22 + $b * 20), ($h + $i * 8) % 360, 52 + $i * 4), range(0, 5))),
        };

        return sprintf('<svg class="p-art %s" viewBox="0 0 200 120" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="200" height="120" fill="hsl(%d 60%% 92%%)"/>%s<text x="14" y="106" class="p-ini" fill="hsl(%d 55%% 26%%)">%s</text></svg>', $big ? 'big' : '', $h, $shape, $h, self::e($ini));
    }
}
