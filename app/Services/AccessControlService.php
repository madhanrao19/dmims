<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Centralised access validation (TDD §12). Combines user status, company status,
 * subscription status, license status, module access and permissions into the
 * effective access decisions used across the platform.
 *
 * Subscription answers "what should the customer be entitled to?"; license
 * answers "can the customer technically use the system now?" (TDD §14–16).
 */
class AccessControlService
{
    public const MODE_FULL = 'full';

    public const MODE_VIEW_ONLY = 'view_only';

    public const MODE_BLOCKED = 'blocked';

    /** Per-request memo so repeated can() checks don't re-query. */
    private static array $modeCache = [];

    public function __construct(private ModuleAccessService $modules) {}

    /**
     * Whether the user may authenticate and use the system at all.
     */
    public function canLogin(User $user): bool
    {
        return $this->loginDenialReason($user) === null;
    }

    /**
     * Same access check as canLogin(), but returns the specific human-
     * readable reason for a denial instead of a bare bool — shown on the
     * login form only after the password has already been verified correct
     * (see Filament\Auth\Login::isUserAllowedToAccessPanel), so surfacing
     * the reason here isn't an account-enumeration risk: the user already
     * proved they own the account.
     */
    public function loginDenialReason(User $user): ?string
    {
        // `default` is kept even though the `status` column is a DB-level
        // enum of exactly these 7 values (create_users_table migration):
        // this same match backs canAccessPanel(), which Filament's auth
        // middleware runs on every authenticated request, not just login.
        // Without a default, a status the enum doesn't currently list (a
        // future migration adding one without this match being updated, or
        // a raw DB write) would throw UnhandledMatchError — a 500 on every
        // page for an already-logged-in user, not a clean denial. Fail
        // closed with a generic message and log it instead.
        $statusReason = match ($user->status) {
            'active' => null,
            'pending' => 'Your account has not been activated yet. Please contact your administrator.',
            'inactive' => 'Your account is inactive. Please contact your administrator to reactivate it.',
            'suspended' => 'Your account has been suspended. Please contact your administrator.',
            'locked' => 'Your account has been locked. Please contact your administrator to unlock it.',
            'password_expired' => 'Your password has expired. Please use "Forgot password?" below to set a new one.',
            'archived' => 'Your account has been archived and can no longer sign in.',
            default => tap('Your account cannot sign in right now. Please contact your administrator.', function () use ($user) {
                Log::warning('User has an unrecognised status value', ['user_id' => $user->id, 'status' => $user->status]);
            }),
        };

        if ($statusReason !== null) {
            return $statusReason;
        }

        if ($user->is_platform_user) {
            return null;
        }

        // A non-platform user with no customer_id is a data-integrity defect
        // (every tenant-scoped guard below treats "no customer_id" as
        // "unscoped", which would otherwise grant this account full
        // cross-tenant read access) — fail closed rather than let it log in.
        if (! $user->customer_id) {
            return 'Your account is not fully set up. Please contact your administrator.';
        }

        $companyReason = $this->companyDenialReason($user->customer_id);

        if ($companyReason !== null) {
            return $companyReason;
        }

        if ($this->getEffectiveAccessMode($user->customer_id) === self::MODE_BLOCKED) {
            return $this->licenseDenialReason($user->customer_id);
        }

        return null;
    }

    /**
     * Resolve the technical access mode for a customer from their license.
     * Platform users and unlicensed customers default to full access.
     */
    public function getEffectiveAccessMode(?int $customerId): string
    {
        if (! $customerId) {
            return self::MODE_FULL;
        }

        if (array_key_exists($customerId, self::$modeCache)) {
            return self::$modeCache[$customerId];
        }

        $license = License::where('customer_id', $customerId)
            ->latest('valid_to')
            ->first();

        return self::$modeCache[$customerId] = $this->modeFromLicense($license);
    }

    public function canView(User $user): bool
    {
        return $user->is_platform_user
            || $this->getEffectiveAccessMode($user->customer_id) !== self::MODE_BLOCKED;
    }

    public function canExport(User $user): bool
    {
        // Exports are read operations, allowed unless access is fully blocked.
        return $this->canView($user);
    }

    public function canPerformOperationalAction(User $user): bool
    {
        if ($user->is_platform_user) {
            return true;
        }

        return $this->getEffectiveAccessMode($user->customer_id) === self::MODE_FULL;
    }

    /**
     * Effective usage limits from the customer's active subscription.
     *
     * @return array<string, int|null>
     */
    public function getEffectiveLimits(?int $customerId): array
    {
        if (! $customerId) {
            return [];
        }

        $subscription = CustomerSubscription::where('customer_id', $customerId)
            ->whereIn('status', ['active', 'near_expiry', 'trial'])
            ->latest('valid_to')
            ->first();

        if (! $subscription) {
            return [];
        }

        return [
            'max_users' => $subscription->max_users,
            'max_products' => $subscription->max_products,
            'max_document_files' => $subscription->max_document_files,
            'max_boxes' => $subscription->max_boxes,
        ];
    }

    public function moduleEnabled(?int $customerId, string $moduleCode): bool
    {
        return ! $customerId || $this->modules->isModuleEnabled($customerId, $moduleCode);
    }

    private function modeFromLicense(?License $license): string
    {
        // No license issued yet: degrade to view-only (same as Suspended/
        // Expired in Business Rules §8) rather than granting unrestricted
        // full access — full access must be earned by an actual license,
        // not its absence.
        if (! $license) {
            return self::MODE_VIEW_ONLY;
        }

        if ($license->technical_access_mode === self::MODE_BLOCKED
            || in_array($license->status, ['cancelled', 'revoked'], true)) {
            return self::MODE_BLOCKED;
        }

        // Past validity (plus any grace period) degrades to read-only. The date
        // is authoritative regardless of the `status` column: nothing
        // automatically flips status to `expired`, so gating this on status
        // would let an unmaintained, still-"active" license keep full access
        // after it has actually lapsed. `valid_to` is inclusive (valid through
        // the end of that day + grace).
        if (Carbon::parse($license->valid_to)
            ->addDays((int) $license->grace_period_days)->endOfDay()->isPast()) {
            return self::MODE_VIEW_ONLY;
        }

        if ($license->technical_access_mode === self::MODE_VIEW_ONLY
            || in_array($license->status, ['suspended', 'expired', 'restricted'], true)) {
            return self::MODE_VIEW_ONLY;
        }

        return self::MODE_FULL;
    }

    /**
     * Whether the company is in a status that permits login at all, and if
     * not, why. Trial, Active, and Near Expiry get normal access (Business
     * Rules §4); Expired is "controlled by subscription grace period and
     * license" and Suspended gets "view-only access if permitted by
     * license" — both are meant to degrade via the license/subscription
     * layers (getEffectiveAccessMode), not be hard-blocked here. Only
     * Cancelled and Archived are terminal.
     */
    private function companyDenialReason(int $customerId): ?string
    {
        $status = Customer::whereKey($customerId)->value('status');

        return match ($status) {
            'cancelled' => 'Your organization\'s subscription has been cancelled. Please contact support for assistance.',
            'archived' => 'Your organization\'s account has been archived. Please contact support for assistance.',
            default => null,
        };
    }

    /**
     * Explains a MODE_BLOCKED license outcome — only called once
     * getEffectiveAccessMode() has already determined access is blocked, so
     * this only needs to describe why, not re-derive the mode.
     */
    private function licenseDenialReason(int $customerId): string
    {
        $license = License::where('customer_id', $customerId)
            ->latest('valid_to')
            ->first();

        if ($license && in_array($license->status, ['cancelled', 'revoked'], true)) {
            return 'Your organization\'s license has been '.$license->status.'. Please contact your administrator or support.';
        }

        return 'Your organization\'s subscription or license has expired. Please contact your administrator or support to restore access.';
    }

    /** Testing helper — clear the per-request memo. */
    public static function flushCache(): void
    {
        self::$modeCache = [];
    }
}
