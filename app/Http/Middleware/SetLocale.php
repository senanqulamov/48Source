<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uses the language the signed-in user chose. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;
        if ($locale && isset(config('procurement.locales')[$locale])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
