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

        // Bind a tenant user's records to their own customer on every write.
        // `saving` covers both create and update: forcing customer_id here (not
        // just when empty) closes the gap where a crafted create OR an edit that
        // changes the customer_id select could otherwise write into — or move a
        // record out to — another tenant. customer_id is mass-assignable on the
        // operational models, so this server-side guard is the authority, never
        // the request. Platform users and unauthenticated contexts (seeders,
        // queued jobs, console) keep whatever they set.
        static::saving(function (Model $model): void {
            $user = auth()->user();

            if ($user && ! $user->is_platform_user && $user->customer_id) {
                $model->customer_id = $user->customer_id;
            }
        });
    }
}
