<?php

namespace App\Http\Middleware;

use App\Support\StudioAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The studio is open to whoever entered the team password in this session. */
class EnsureStudioAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (StudioAccess::granted($request)) {
            return $next($request);
        }
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(401);
        }

        return redirect()->guest(route('studio.login'));
    }
}
