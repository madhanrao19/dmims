<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side verification for Cloudflare Turnstile — the client-side widget
 * alone proves nothing; every submission must be re-checked against
 * Cloudflare's siteverify endpoint with the secret key before trusting it.
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Whether the widget should render at all — both keys must be configured. */
    public function isEnabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * @param  string|null  $token  the `cf-turnstile-response` field submitted by the widget
     */
    public function verify(?string $token, ?string $remoteIp): bool
    {
        if (! $this->isEnabled()) {
            // No keys configured (local/dev) — the widget was never rendered,
            // so there is nothing to verify. Fails closed the moment either
            // key is set, which is the production expectation.
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);
        } catch (\Throwable $e) {
            // Fail closed: a network error to Cloudflare must not silently
            // let every login through unverified.
            Log::warning('Turnstile verification request failed', ['error' => $e->getMessage()]);

            return false;
        }

        return (bool) ($response->json('success') ?? false);
    }
}
