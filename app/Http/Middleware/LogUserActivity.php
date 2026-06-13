<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Record only meaningful, top-level state changes (login, logout,
        // password reset, custom POST routes). Previously this logged EVERY
        // authenticated request — including Livewire polling and asset loads —
        // which flooded audit_logs with useless "POST livewire/update" rows
        // that carried no entity context. Per-entity changes are captured at
        // the model layer via the Auditable trait instead.
        if (auth()->check() && $this->shouldLog($request)) {
            app(AuditService::class)->record([
                'customer_id' => auth()->user()->customer_id,
                'user_id' => auth()->id(),
                'module' => 'user_activity',
                'action' => $request->method().' '.$request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }

    protected function shouldLog(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        // Livewire/Filament route all panel interactions through a single
        // internal endpoint; those are logged per-entity by the Auditable
        // trait, not here.
        return ! $request->is('livewire/*');
    }
}
