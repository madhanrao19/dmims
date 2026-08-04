<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

/**
 * Operational follow-up for v2.1.18: AccessControlService::modeFromLicense()
 * now degrades a customer with no License row to view_only instead of full
 * access. This lists customers who would be affected by that change so ops
 * can issue licenses proactively before/at deploy, or knowingly accept the
 * degraded access. Read-only — does not modify anything.
 */
class ListUnlicensedActiveCustomers extends Command
{
    protected $signature = 'dmims:unlicensed-active-customers';

    protected $description = 'List customers with an active/trial subscription but no License row (would lose write access under v2.1.18).';

    public function handle(): int
    {
        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->whereHas('subscriptions', function ($query) {
                $query->whereIn('status', ['active', 'near_expiry', 'trial']);
            })
            ->whereDoesntHave('licenses')
            ->get(['id', 'company_name', 'company_code', 'email', 'status']);

        if ($customers->isEmpty()) {
            $this->info('No active/trial customers are missing a license. Nothing to do.');

            return self::SUCCESS;
        }

        $this->warn("{$customers->count()} customer(s) have an active/trial subscription but no license — they will lose write access under v2.1.18 until one is issued:");
        $this->table(
            ['ID', 'Company', 'Code', 'Email', 'Status'],
            $customers->map(fn ($c) => [$c->id, $c->company_name, $c->company_code, $c->email, $c->status])
        );

        return self::SUCCESS;
    }
}
