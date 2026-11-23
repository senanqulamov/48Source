<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer-only money and quantity arithmetic.
 * Amounts are minor units (cents); quantities are thousandths.
 */
final class Money
{
    public const SYMBOLS = ['USD' => '$', 'EUR' => '€', 'AZN' => '₼'];

    /** Parse "1,234.50" or "1234,5" into cents. Returns null for an empty value. */
    public static function parse(string|int|null $value): ?int
    {
        return self::scaled($value, 2);
    }

    /** Parse a quantity such as "12.5" into thousandths. */
    public static function parseQty(string|int|null $value): ?int
    {
        return self::scaled($value, 3);
    }

    private static function scaled(string|int|null $value, int $scale): ?int
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        $v = str_replace([' ', "\u{00A0}"], '', $v);
        // "1,234.50" -> thousands commas; "1234,5" -> decimal comma
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }
        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,'.$scale.'}))?$/', $v, $m)) {
            throw new InvalidArgumentException('Not a valid amount: '.$value);
        }

        return (int) $m[1] * (10 ** $scale) + (int) str_pad($m[2] ?? '', $scale, '0');
    }

    /** price (cents) x quantity (thousandths), rounded half up to the cent. */
    public static function lineTotal(int $priceCents, int $qtyMilli): int
    {
        return intdiv($priceCents * $qtyMilli + 500, 1000);
    }

    public static function format(?int $cents, string $currency = 'USD', bool $decimals = true): string
    {
        if ($cents === null) {
            return '-';
        }
        $neg = $cents < 0;
        $abs = abs($cents);
        $whole = number_format(intdiv($abs, 100));
        $out = (self::SYMBOLS[$currency] ?? $currency.' ').$whole.($decimals ? '.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT) : '');

        return ($neg ? '-' : '').$out;
    }

    public static function qty(int $milli): string
    {
        $whole = number_format(intdiv($milli, 1000));
        $frac = rtrim(str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT), '0');

        return $frac === '' ? $whole : $whole.'.'.$frac;
    }

    /** Plain decimal string for form inputs, e.g. 12550 -> "125.50". */
    public static function input(?int $cents): string
    {
        return $cents === null ? '' : intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function qtyInput(?int $milli): string
    {
        return $milli === null ? '' : str_replace(',', '', self::qty($milli));
    }
}
