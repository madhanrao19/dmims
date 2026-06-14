<?php

use App\Http\Middleware\EnsureCompanyActive;
use App\Http\Middleware\EnsureCompanyAssigned;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\InjectPwaScript;
use App\Http\Middleware\LogUserActivity;
use App\Http\Middleware\SetCompanyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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

        $middleware->append([
            SetCompanyContext::class,
            EnsureUserIsActive::class,
            EnsureCompanyAssigned::class,
            EnsureCompanyActive::class,
            EnsureSubscriptionActive::class,
            LogUserActivity::class,
            InjectPwaScript::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
