<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyActive
{
    /**
     * Same fix and same reasoning as AccessControlService::companyActive():
     * Business Rules §4 gives Trial/Active/Near Expiry normal access, and
     * says Expired/Suspended degrade via the license layer rather than being
     * hard-blocked here. Only Cancelled/Archived are terminal. This
     * middleware previously only allowed 'active', so it re-blocked every
     * request for a trial/suspended company even after login succeeded.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->customer_id) {
            $company = Customer::find($user->customer_id);

            if (! $company || in_array($company->status, ['cancelled', 'archived'], true)) {
                abort(403, 'Company is not active.');
            }
        }

        return $next($request);
    }
}
