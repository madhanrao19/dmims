<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers, documented as an Apache requirement in the Ops
 * guide and DEFINITION_OF_DONE's security checklist but never actually set
 * anywhere (neither Apache nor the app). Applied here instead of Apache so
 * they're present regardless of the web server in front of PHP-FPM.
 *
 * HSTS is conditional on the request actually being HTTPS: this server is
 * intentionally reachable both via the Cloudflare Tunnel (HTTPS) and
 * directly on the LAN (plain HTTP, no cert — see SESSION_SECURE_COOKIE's
 * comment in DEPLOYMENT_GUIDE.md). Sending HSTS unconditionally would tell
 * browsers to force HTTPS for the LAN host too, breaking that documented
 * access path.
 *
 * No Content-Security-Policy here deliberately — Livewire/Alpine's inline
 * script usage needs a carefully tested nonce-based policy to avoid breaking
 * the admin panel; a naive CSP is a common way to silently break a Filament
 * app, so this is left as a follow-up rather than shipped unverified.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
