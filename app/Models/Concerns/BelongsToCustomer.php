<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Restricts queries to the authenticated user's customer (tenant) as a
 * defence-in-depth layer behind the Filament resource scoping.
 *
 * The scope only applies when a non-platform user is authenticated. Platform
 * users, console commands, queued jobs, and seeders (no authenticated user)
 * are intentionally unscoped so cross-tenant administration still works.
 *
 * Security & Access Control Matrix §3.2 (TENANT_STRICT): a null customer_id
 * is NOT automatically visible to customer users — only models that
 * explicitly opt in by overriding includesGlobalCustomerDefaults() to
 * return true (§3.3 TENANT_WITH_GLOBAL_DEFAULTS, e.g. shared default
 * Document Types) include null-owned rows.
 */
trait BelongsToCustomer
{
    /**
     * Override on a model to opt into TENANT_WITH_GLOBAL_DEFAULTS. A method
     * (not a property) because PHP forbids a using class from redeclaring a
     * trait's typed property with a different default value.
     */
    protected static function includesGlobalCustomerDefaults(): bool
    {
        return false;
    }

    public static function bootBelongsToCustomer(): void
    {
        static::addGlobalScope('customer', function (Builder $builder): void {
            $user = auth()->user();

            if (! $user || $user->is_platform_user) {
                return;
            }

            // A non-platform user with no customer_id is a data-integrity
            // defect (should never log in per AccessControlService::canLogin()),
            // but this scope is the last line of defence against exactly
            // that: fail closed (no rows) instead of falling through to
            // "unscoped", which would otherwise hand such an account
            // cross-tenant read access.
            if (! $user->customer_id) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $table = $builder->getModel()->getTable();

            if (! static::includesGlobalCustomerDefaults()) {
                $builder->where("{$table}.customer_id", $user->customer_id);

                return;
            }

            $builder->where(function (Builder $query) use ($table, $user): void {
                $query->where("{$table}.customer_id", $user->customer_id)
                    ->orWhereNull("{$table}.customer_id");
            });
        });

        static::creating(function (Model $model): void {
            /** @var self $model */
            $user = auth()->user();

            // Always bind a tenant user's records to their own customer,
            // overriding any customer_id supplied by the caller. customer_id is
            // mass-assignable on the operational models, so forcing it here (not
            // just when empty) closes the gap where a crafted create could
            // otherwise write into another tenant. Platform users and unauthenticated
            // contexts (seeders, queued jobs, console) keep whatever they set.
            if ($user && ! $user->is_platform_user && $user->customer_id) {
                $model->customer_id = $user->customer_id;
            }
        });

        static::updating(function (Model $model): ?bool {
            /** @var self $model */
            $user = auth()->user();

            if (! $user || $user->is_platform_user || ! $user->customer_id) {
                return null;
            }

            // Second line of defence behind BaseResource::can(): a shared/
            // global-default record (customer_id = null, only reachable for
            // TENANT_WITH_GLOBAL_DEFAULTS models) must never be writable by a
            // tenant user — the creating/updating hooks would otherwise
            // silently re-own it to that tenant, deleting it from every
            // other tenant's view. Cancel the save outright rather than
            // rely solely on the authorization layer.
            if (static::includesGlobalCustomerDefaults() && $model->getOriginal('customer_id') === null) {
                return false;
            }

            // Same as creating(): a tenant user editing a record they already own
            // must not be able to reassign it to another tenant by submitting a
            // different customer_id on the edit form.
            $model->customer_id = $user->customer_id;

            return null;
        });
    }
}
