<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            session(['customer_id' => auth()->user()->customer_id]);
        }

        return $next($request);
    }
}
