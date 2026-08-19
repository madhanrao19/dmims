<?php

namespace App\Filament\Resources\Pages\Concerns;

/**
 * The customer_id Select on every $applyCustomerScope resource's form is
 * only ever hidden via ->visible() for a tenant-scoped actor, never
 * ->disabled() or excluded from dehydration — so nothing stops a crafted
 * Livewire request from submitting a different company's ID. Today that's
 * mitigated only by Customer::class's own global scope narrowing the
 * dropdown to one option (confirmed by security review of the Users,
 * Products, Document Files, Billing, and Backups modules) — this is the
 * missing second layer, matching the pattern UserResource already used for
 * itself. Force it back to the actor's own tenant, server-side, on every
 * create and edit.
 */
trait ForcesOwnCustomerId
{
    protected function forceOwnCustomerId(array $data): array
    {
        $user = auth()->user();

        if ($user && ! $user->is_platform_user && $user->customer_id && array_key_exists('customer_id', $data)) {
            $data['customer_id'] = $user->customer_id;
        }

        return $data;
    }
}
