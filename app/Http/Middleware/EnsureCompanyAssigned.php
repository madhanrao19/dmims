<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAssigned
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->customer_id === null && ! auth()->user()->is_platform_user) {
            abort(403, 'No company assigned.');
        }

        return $next($request);
    }
}
