<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Carbon;

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
        if ($user->status !== 'active') {
            return false;
        }

        if ($user->is_platform_user) {
            return true;
        }

        if ($user->customer_id && ! $this->companyActive($user->customer_id)) {
            return false;
        }

        return $this->getEffectiveAccessMode($user->customer_id) !== self::MODE_BLOCKED;
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
        // No license issued yet: do not lock the customer out.
        if (! $license) {
            return self::MODE_FULL;
        }

        if ($license->technical_access_mode === self::MODE_BLOCKED
            || in_array($license->status, ['cancelled', 'revoked'], true)) {
            return self::MODE_BLOCKED;
        }

        // Expired beyond grace also blocks operational use.
        if ($license->valid_to && Carbon::parse($license->valid_to)
            ->addDays((int) $license->grace_period_days)->isPast()
            && ! in_array($license->status, ['active', 'trial', 'near_expiry'], true)) {
            return self::MODE_VIEW_ONLY;
        }

        if ($license->technical_access_mode === self::MODE_VIEW_ONLY
            || in_array($license->status, ['suspended', 'expired', 'restricted'], true)) {
            return self::MODE_VIEW_ONLY;
        }

        return self::MODE_FULL;
    }

    private function companyActive(int $customerId): bool
    {
        return Customer::whereKey($customerId)
            ->where('status', 'active')
            ->exists();
    }

    /** Testing helper — clear the per-request memo. */
    public static function flushCache(): void
    {
        self::$modeCache = [];
    }
}
