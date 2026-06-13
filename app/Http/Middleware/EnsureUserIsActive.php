<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->status !== 'active') {
            abort(403, 'Your account is not active.');
        }

        return $next($request);
    }
}
