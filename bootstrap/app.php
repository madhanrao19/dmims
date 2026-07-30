<?php

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\EnsureCompanyActive;
use App\Http\Middleware\EnsureCompanyAssigned;
use App\Http\Middleware\EnsureLicenseAllowsAccess;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\InjectPwaScript;
use App\Http\Middleware\LogUserActivity;
use App\Http\Middleware\SetCompanyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare/Nginx the real client IP arrives via forwarded
        // headers; trust them so auth, rate limiting and audit logs see it.
        $proxies = (string) env('TRUSTED_PROXIES', '');
        if ($proxies !== '') {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
            );
        }

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        $middleware->prepend([
            AssignRequestContext::class,
        ]);

        $middleware->append([
            LogUserActivity::class,
            InjectPwaScript::class,
        ]);

        // These all read auth()->user(), so they must run AFTER the request
        // has been authenticated (session guard on the Filament panel, token
        // guard on the API). $middleware->append() adds to the GLOBAL stack,
        // which runs before any route-group middleware — including the
        // panel's own StartSession and routes/api.php's auth:sanctum — so
        // placed there they silently no-op on every request (auth()->user()
        // is always null at that point). Registered as a named group instead
        // and attached after authentication in FilamentPanelProvider and
        // routes/api.php.
        $middleware->group('business-access', [
            SetCompanyContext::class,
            EnsureUserIsActive::class,
            EnsureCompanyAssigned::class,
            EnsureCompanyActive::class,
            EnsureSubscriptionActive::class,
            EnsureLicenseAllowsAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
