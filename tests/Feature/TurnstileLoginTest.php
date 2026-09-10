<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use App\Services\TurnstileVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TurnstileLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.turnstile.site_key' => 'test-site-key', 'services.turnstile.secret_key' => 'test-secret-key']);
    }

    public function test_verifier_is_disabled_when_keys_are_not_configured(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

        $verifier = app(TurnstileVerifier::class);

        $this->assertFalse($verifier->isEnabled());
        // Fails open only when disabled — no widget was ever rendered to produce a token.
        $this->assertTrue($verifier->verify(null, '127.0.0.1'));
    }

    public function test_verifier_rejects_a_blank_token_when_enabled(): void
    {
        $verifier = app(TurnstileVerifier::class);

        $this->assertTrue($verifier->isEnabled());
        $this->assertFalse($verifier->verify(null, '127.0.0.1'));
    }

    public function test_verifier_returns_true_when_cloudflare_reports_success(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(app(TurnstileVerifier::class)->verify('good-token', '127.0.0.1'));
    }

    public function test_verifier_returns_false_when_cloudflare_reports_failure(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $this->assertFalse(app(TurnstileVerifier::class)->verify('bad-token', '127.0.0.1'));
    }

    public function test_verifier_fails_closed_on_a_network_error(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('timed out');
        });

        $this->assertFalse(app(TurnstileVerifier::class)->verify('any-token', '127.0.0.1'));
    }

    public function test_login_is_rejected_when_turnstile_token_is_missing(): void
    {
        $user = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->set('data.turnstile_token', null)
            ->call('authenticate')
            ->assertHasErrors(['data.turnstile_token']);

        $this->assertGuest();
    }

    public function test_login_succeeds_when_turnstile_verification_passes(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $user = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->set('data.turnstile_token', 'good-token')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }
}
