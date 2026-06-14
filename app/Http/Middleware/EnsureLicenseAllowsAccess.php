<?php

namespace App\Http\Middleware;

use App\Services\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * License validation layer (SAD layer 5, TDD §13/§15). A blocked license denies
 * all access; view-only restriction is enforced at the authorisation layer
 * (BaseResource::can) so users can still read but not modify.
 */
class EnsureLicenseAllowsAccess
{
    public function __construct(private AccessControlService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && ! $user->is_platform_user && $user->customer_id) {
            if ($this->access->getEffectiveAccessMode($user->customer_id) === AccessControlService::MODE_BLOCKED) {
                abort(403, 'Your license does not currently permit access. Please contact Datamation.');
            }
        }

        return $next($request);
    }
}
