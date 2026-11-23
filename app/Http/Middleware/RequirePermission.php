<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard: perm:module,ability - for example perm:rfqs,C. */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $module, string $ability = 'V'): Response
    {
        abort_unless($request->user()?->allowed($module, $ability), 403);

        return $next($request);
    }
}
