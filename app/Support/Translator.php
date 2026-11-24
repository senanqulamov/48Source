<?php

namespace App\Support;

/**
 * Translates the interface texts of a rendered page.
 *
 * The views are written in English. For any other language the finished HTML
 * is passed through here: every text between tags and every hint attribute is
 * looked up in lang/ui/{locale}.php (plus lang/{locale}.json) and replaced
 * when a translation exists. Text that is not in the dictionary - names,
 * references, anything a person typed - is left exactly as it is.
 */
class Translator
{
    private const ATTRIBUTES = 'placeholder|title|aria-label|data-tip|data-tour|data-empty|alt';

    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    /** @return array<string, string> */
    public static function dictionary(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }
        $words = [];
        $json = lang_path($locale.'.json');
        if (is_file($json)) {
            foreach ((array) json_decode((string) file_get_contents($json), true) as $k => $v) {
                if (is_string($v) && ! str_contains($k, ':')) {
                    $words[$k] = $v;
                }
            }
        }
        $ui = lang_path('ui/'.$locale.'.php');
        if (is_file($ui)) {
            $words = array_merge($words, require $ui);
        }

        return self::$cache[$locale] = $words;
    }

    public static function has(string $locale): bool
    {
        return $locale !== 'en' && self::dictionary($locale) !== [];
    }

    /** The few texts the browser script writes itself (calendar, select search, time picker). */
    public static function forScripts(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        if (! self::has($locale)) {
            return [];
        }
        $words = self::dictionary($locale);
        $out = [];
        foreach (['Clear', 'Done', 'Nothing matches', 'Recently opened', 'Time', 'Guided tour', 'Hour down', 'Hour up', 'Hour', 'Minutes down', 'Minutes up', 'Minutes', 'Next month', 'Previous month', 'Search options', 'Search', 'In a week', 'In 2 weeks', 'In a month', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su', 'Select a date', 'Select date and time', 'Go to', 'No results for', 'Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $key) {
            if (isset($words[$key])) {
                $out[$key] = $words[$key];
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function patterns(string $locale): array
    {
        static $all = null;
        $all ??= is_file(lang_path('ui/patterns.php')) ? require lang_path('ui/patterns.php') : [];
        $out = [];
        foreach ($all as $regex => $per) {
            if (isset($per[$locale])) {
                $out[$regex] = $per[$locale];
            }
        }

        return $out;
    }

    /** One text, or the same text back when it is not in the dictionary. */
    public static function text(string $text, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if (! self::has($locale)) {
            return $text;
        }

        return self::lookup($locale, $text) ?? $text;
    }

    /** Translate a source message before it becomes a toast, email, notification or PDF string. */
    public static function format(string $text, array $replace = [], ?string $locale = null): string
    {
        foreach ($replace as $key => $value) {
            $text = str_replace(':'.$key, (string) $value, $text);
        }

        return self::text($text, $locale);
    }

    /** @return array<int, string> Interface-looking text still unknown to the dictionary. */
    public static function untranslated(string $html, string $locale): array
    {
        if (! self::has($locale)) {
            return [];
        }
        $values = [];
        $knownTranslations = array_fill_keys(self::dictionary($locale), true);
        preg_match_all('~(?<=>)([^<>]+)(?=<)|(?:placeholder|title|aria-label|data-tip|data-tour|data-empty)="([^"]+)"~u', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $raw = trim(html_entity_decode($match[1] !== '' ? $match[1] : $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            // User-entered values tend to contain a reference, number, e-mail address or URL.
            $translatedParts = str_contains($raw, ':') && collect(explode(':', $raw))->filter()->every(fn ($part) => isset($knownTranslations[trim($part)]));
            if ($raw === '' || $translatedParts || isset($knownTranslations[$raw]) || mb_strlen($raw) > 240 || ! preg_match('~^[\x20-\x7E]+$~', $raw) || ! preg_match('~[A-Za-z]{2}~', $raw) || preg_match('~[\d@/\\\\]|https?://|^\.|^[A-Z_]{1,}$~', $raw) || self::lookup($locale, $raw) !== null) {
                continue;
            }
            $values[$raw] = true;
        }

        return array_keys($values);
    }

    public static function html(string $html, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if ($html === '' || ! self::has($locale)) {
            return $html;
        }

        // Script, style and typed text are set aside so they are never touched.
        $kept = [];
        $html = (string) preg_replace_callback('~<(script|style|pre|code)\b[^>]*>.*?</\1>~is', function ($m) use (&$kept) {
            $kept[] = $m[0];

            return "\x02".(count($kept) - 1)."\x03";
        }, $html);
        // What a person typed into a text area stays; its placeholder is still translated.
        $html = (string) preg_replace_callback('~(<textarea\b[^>]*>)(.*?)(</textarea>)~is', function ($m) use (&$kept) {
            $kept[] = $m[2];

            return $m[1]."\x02".(count($kept) - 1)."\x03".$m[3];
        }, $html);

        $html = (string) preg_replace_callback('~(?<=>)([^<>\x02\x03]+)(?=<)~u', fn ($m) => self::swap($locale, $m[1]), $html);

        $html = (string) preg_replace_callback('~(\s(?:'.self::ATTRIBUTES.')=")([^"]+)(")~u', fn ($m) => $m[1].self::swap($locale, $m[2]).$m[3], $html);

        return (string) preg_replace_callback('~\x02(\d+)\x03~', fn ($m) => $kept[(int) $m[1]], $html);
    }

    /** Keeps the surrounding white space of a text node and replaces its words. */
    private static function swap(string $locale, string $raw): string
    {
        if (! preg_match('~^(\s*)(.*?)(\s*)$~us', $raw, $p) || $p[2] === '' || ! preg_match('~\pL{2}~u', $p[2])) {
            return $raw;
        }
        $plain = html_entity_decode($p[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $found = self::lookup($locale, $plain);

        return $found === null ? $raw : $p[1].htmlspecialchars($found, ENT_QUOTES, 'UTF-8', false).$p[3];
    }

    private const MONTHS = 'Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec';

    private static function lookup(string $locale, string $text, int $depth = 0): ?string
    {
        $words = self::dictionary($locale);
        $text = trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $text));
        if ($text === '' || mb_strlen($text) > 400) {
            return null;
        }
        if (isset($words[$text])) {
            return $words[$text];
        }
        if ($depth > 2) {
            return null;
        }
        // "A · B · C": each part on its own.
        if (str_contains($text, '·')) {
            $hit = false;
            $parts = array_map(function ($part) use ($locale, $depth, &$hit) {
                $t = trim($part);
                $found = $t === '' ? null : self::lookup($locale, $t, $depth + 1);
                $hit = $hit || $found !== null;

                return $found ?? $t;
            }, explode('·', $text));

            return $hit ? trim(implode(' · ', $parts)) : null;
        }
        // "3 Dec", "3 Dec 2026, 14:00": only the month changes.
        if (preg_match('~^(\d{1,2}) ('.self::MONTHS.')((?: \d{4})?(?:,? \d{1,2}:\d{2})?)$~', $text, $m) && isset($words[$m[2]])) {
            return $m[1].' '.$words[$m[2]].$m[3];
        }
        // "12 suppliers", "3 selected": a number followed by a known word.
        if (preg_match('~^([\d][\d.,]*)\s(.+)$~u', $text, $m) && isset($words[$m[2]])) {
            return $m[1].' '.$words[$m[2]];
        }
        // "Reason:", "Loading...": a known text with a trailing mark.
        if (preg_match('~^(.+?)(\s?[:*…]|\.\.\.)$~u', $text, $m) && isset($words[$m[1]])) {
            return $words[$m[1]].$m[2];
        }
        foreach (self::patterns($locale) as $regex => $template) {
            if (preg_match($regex, $text, $m)) {
                return (string) preg_replace_callback('~\{(t?)(\d)\}~', function ($k) use ($m, $locale, $depth, $words) {
                    $value = $m[(int) $k[2]] ?? '';
                    if ($k[1] !== 't') {
                        return $value;
                    }
                    foreach ([$value, mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1), mb_strtolower(mb_substr($value, 0, 1)).mb_substr($value, 1)] as $try) {
                        if (isset($words[$try])) {
                            return $words[$try];
                        }
                    }

                    return self::lookup($locale, $value, $depth + 1) ?? $value;
                }, $template);
            }
        }

        return null;
    }
}
