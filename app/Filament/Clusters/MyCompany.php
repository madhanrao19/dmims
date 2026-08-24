<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * Security & Access Control Matrix §5 / Business Rules §5.1 / TDD §7.3:
 * consolidates customer-facing company administration (Profile, Users,
 * Enabled Modules, Subscription, License Status, Billing, Audit Logs) under
 * one navigation entry instead of scattered top-level resources. Each tab
 * is its own Page under App\Filament\Clusters\MyCompany\Pages, independently
 * authorized by delegating to the existing resource's own can()/query — see
 * Pages\Concerns\HasEmbeddedResourceTable. Platform users keep using the
 * separate multi-tenant resources (Customers, Users, etc.) unchanged; this
 * cluster only ever shows a single (the acting user's own) customer's data.
 */
class MyCompany extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'My Company';

    protected static ?int $navigationSort = -1;

    /**
     * Only meaningful for a tenant user with a company — platform staff
     * administer every company through the existing dedicated resources
     * instead, and have no "own company" to show here.
     */
    private static function isTenantUserWithCompany(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ! $user->is_platform_user && $user->customer_id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::isTenantUserWithCompany() && parent::shouldRegisterNavigation();
    }

    /**
     * Security review finding (24 August 2026): Cluster's own canAccess()
     * defaults to true (Filament\Pages\Concerns\CanAuthorizeAccess), so
     * without this override the cluster's own route (/admin/my-company)
     * stayed reachable — 403 or a broken redirect for a platform user, and
     * (harmlessly, since every sub-page independently re-checks its own
     * canAccess()) reachable-but-empty for a tenant role with zero
     * accessible tabs. No individual tab's data is exposed either way, but
     * the route itself should 403 like everything else in this cluster.
     */
    public static function canAccess(): bool
    {
        return self::isTenantUserWithCompany() && static::canAccessClusteredComponents();
    }
}
