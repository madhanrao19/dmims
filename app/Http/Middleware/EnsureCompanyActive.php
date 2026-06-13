<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->customer_id) {
            $company = Customer::find($user->customer_id);

            if (! $company || $company->status !== 'active') {
                abort(403, 'Company is not active.');
            }
        }

        return $next($request);
    }
}
