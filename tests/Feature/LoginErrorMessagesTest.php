<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Customer;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the specific, non-silent login rejection messages
 * added to App\Filament\Auth\Login + AccessControlService::loginDenialReason().
 * Before this, every rejection after a correct password (suspended, locked,
 * inactive, pending, password-expired, archived account; cancelled company;
 * blocked license) fell through to the same generic "these credentials do
 * not match our records." — indistinguishable from a wrong password.
 */
class LoginErrorMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests are about AccessControlService's denial-reason
        // messages, not Turnstile — Login::authenticate() checks Turnstile
        // first, so a real key pair loaded from .env (there's no
        // .env.testing) would block every attempt here before it ever
        // reaches the status/company/license checks under test.
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);
    }

    private function attemptLogin(User $user): Testable
    {
        return Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->call('authenticate');
    }

    public function test_wrong_password_still_shows_the_generic_message(): void
    {
        $user = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['data.email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    /**
     * Pins the account-enumeration-safety property: retrieveByCredentials()
     * failing (no such user) must show the exact same generic message as
     * validateCredentials() failing (wrong password) — never anything that
     * would let an attacker distinguish "no such account" from "wrong
     * password" for an account that does exist.
     */
    public function test_nonexistent_email_shows_the_same_generic_message(): void
    {
        Livewire::test(Login::class)
            ->set('data.email', 'no-such-user@example.com')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    /**
     * L1 from the security review: an unrecognised status value (the enum
     * changing without this match being updated, or a raw DB write) must
     * fail closed with a generic message, not throw UnhandledMatchError —
     * that match also backs canAccessPanel(), which runs on every
     * authenticated request, so an uncaught error there would 500 an
     * already-logged-in user on every page rather than cleanly denying.
     */
    public function test_unrecognised_status_value_fails_closed_instead_of_throwing(): void
    {
        $user = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        // Bypass the model's enum-backed attribute assignment to simulate a
        // status the app-level enum doesn't know about (e.g. a future
        // migration adding one without this match being updated).
        \DB::table('users')->where('id', $user->id)->update(['status' => 'some_future_status']);

        $this->attemptLogin($user->fresh())->assertHasErrors([
            'data.email' => 'Your account cannot sign in right now. Please contact your administrator.',
        ]);

        $this->assertGuest();
    }

    private static function accountStatusMessages(): array
    {
        return [
            'pending' => 'Your account has not been activated yet. Please contact your administrator.',
            'inactive' => 'Your account is inactive. Please contact your administrator to reactivate it.',
            'suspended' => 'Your account has been suspended. Please contact your administrator.',
            'locked' => 'Your account has been locked. Please contact your administrator to unlock it.',
            'password_expired' => 'Your password has expired. Please use "Forgot password?" below to set a new one.',
            'archived' => 'Your account has been archived and can no longer sign in.',
        ];
    }

    /** Covers "applies to all types of users including Super Admin" — platform users go through the same status check. */
    public function test_platform_user_account_status_shows_specific_message(): void
    {
        foreach (self::accountStatusMessages() as $status => $expectedMessage) {
            // Login's rate limiter is keyed by component+method+IP, not
            // email — six iterations in one test would otherwise trip it
            // after the 5th attempt and swallow the error into a silent
            // throttle notification instead of the message under test.
            Cache::flush();

            $user = User::factory()->create(['is_platform_user' => true, 'status' => $status]);

            $this->attemptLogin($user)->assertHasErrors(['data.email' => $expectedMessage]);

            $this->assertGuest();
        }
    }

    public function test_customer_user_account_status_shows_specific_message(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        foreach (self::accountStatusMessages() as $status => $expectedMessage) {
            Cache::flush();

            $user = User::factory()->create([
                'customer_id' => $customer->id,
                'is_platform_user' => false,
                'status' => $status,
            ]);

            $this->attemptLogin($user)->assertHasErrors(['data.email' => $expectedMessage]);

            $this->assertGuest();
        }
    }

    public function test_cancelled_company_shows_specific_message(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'cancelled']);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        $this->attemptLogin($user)->assertHasErrors([
            'data.email' => 'Your organization\'s subscription has been cancelled. Please contact support for assistance.',
        ]);

        $this->assertGuest();
    }

    public function test_archived_company_shows_specific_message(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'archived']);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        $this->attemptLogin($user)->assertHasErrors([
            'data.email' => 'Your organization\'s account has been archived. Please contact support for assistance.',
        ]);

        $this->assertGuest();
    }

    public function test_cancelled_license_shows_specific_message(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'status' => 'cancelled',
            'technical_access_mode' => 'blocked',
        ]);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        $this->attemptLogin($user)->assertHasErrors([
            'data.email' => 'Your organization\'s license has been cancelled. Please contact your administrator or support.',
        ]);

        $this->assertGuest();
    }

    public function test_revoked_license_shows_specific_message(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'status' => 'revoked',
            'technical_access_mode' => 'full',
        ]);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        $this->attemptLogin($user)->assertHasErrors([
            'data.email' => 'Your organization\'s license has been revoked. Please contact your administrator or support.',
        ]);

        $this->assertGuest();
    }

    /**
     * Business Rules §8: an expired license degrades to VIEW_ONLY, not
     * BLOCKED — login is still allowed (read-only), so an expired license
     * alone must not deny login. Only an explicit technical_access_mode of
     * "blocked", or a cancelled/revoked license status, blocks login.
     */
    public function test_expired_license_alone_still_allows_login(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subYears(2),
            'valid_to' => now()->subYear(),
            'grace_period_days' => 0,
            'status' => 'expired',
            'technical_access_mode' => 'view_only',
        ]);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);

        $this->attemptLogin($user)->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_non_platform_user_with_no_customer_id_shows_specific_message(): void
    {
        $user = User::factory()->create(['is_platform_user' => false, 'customer_id' => null, 'status' => 'active']);

        $this->attemptLogin($user)->assertHasErrors([
            'data.email' => 'Your account is not fully set up. Please contact your administrator.',
        ]);

        $this->assertGuest();
    }
}
