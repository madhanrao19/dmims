<?php

namespace App\Http\Middleware;

use App\Models\CustomerModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $user = auth()->user();

        if ($user && $user->customer_id) {
            $enabled = CustomerModule::where('customer_id', $user->customer_id)
                ->whereHas('module', fn ($query) => $query->where('module_code', $moduleCode))
                ->where('is_enabled', true)
                ->exists();

            if (! $enabled) {
                abort(403, 'Module is not enabled.');
            }
        }

        return $next($request);
    }
}
