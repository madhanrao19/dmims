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
 * Content-Security-Policy is intentionally NOT nonce-based/strict: Filament
 * 5's Livewire/Alpine stack relies on inline <script>/<style> and eval-like
 * Alpine expression evaluation throughout the admin panel, and a strict
 * policy without wiring nonces through every Blade/Livewire render breaks
 * it outright. This is the pragmatic middle ground — same-origin only for
 * scripts/styles/connections, no framing, no plugins — verified against a
 * live admin panel session (login, dashboard, CRUD create/edit, search) with
 * zero CSP violations in the browser console before being added here.
 */
class SecurityHeaders
{
    private const CSP = "default-src 'self'; ".
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
        "style-src 'self' 'unsafe-inline'; ".
        "img-src 'self' data:; ".
        "font-src 'self' data:; ".
        "connect-src 'self'; ".
        "object-src 'none'; ".
        "base-uri 'self'; ".
        "frame-ancestors 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Content-Security-Policy', self::CSP);

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
