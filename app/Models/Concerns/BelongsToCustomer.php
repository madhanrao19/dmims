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
 * Records with a null customer_id are treated as shared and remain visible to
 * everyone, matching the existing application behaviour.
 */
trait BelongsToCustomer
{
    public static function bootBelongsToCustomer(): void
    {
        static::addGlobalScope('customer', function (Builder $builder): void {
            $user = auth()->user();

            if (! $user || $user->is_platform_user || ! $user->customer_id) {
                return;
            }

            $table = $builder->getModel()->getTable();

            $builder->where(function (Builder $query) use ($table, $user): void {
                $query->where("{$table}.customer_id", $user->customer_id)
                    ->orWhereNull("{$table}.customer_id");
            });
        });

        static::creating(function (Model $model): void {
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
    }
}
