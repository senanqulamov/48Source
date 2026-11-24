<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language the signed-in user chose. Visitors who are not signed in
 * can switch with ?lang=az, ?lang=ru or ?lang=en; the choice is kept for the visit.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('procurement.locales');
        $asked = $request->query('lang');

        if (is_string($asked) && isset($locales[$asked]) && $request->hasSession()) {
            $request->session()->put('locale', $asked);
        }

        $locale = $request->user()?->locale ?: ($request->hasSession() ? $request->session()->get('locale') : null);

        if (is_string($locale) && isset($locales[$locale])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
