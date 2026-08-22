<?php

namespace App\Console\Commands;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off remediation for a live bug: the Filament UserResource create/edit
 * form let a platform actor set is_platform_user independently of the
 * assigned role, with nothing checking the two stay consistent. A
 * Datamation Super Admin creating a tenant "Company Admin" user with
 * is_platform_user=true ended up with unrestricted platform-wide access —
 * BaseResource::can() and shouldRegisterNavigation() key off is_platform_user
 * alone and skip tenant scoping entirely once it's true, regardless of role
 * or customer_id. UserResource::enforcePlatformRoleConsistency() now closes
 * this going forward (called from CreateUser::afterCreate/EditUser::afterSave),
 * but only runs when a user record is actually saved through those pages —
 * it does not retroactively fix rows already in a mismatched state. This
 * command finds and corrects those rows directly.
 */
class FixPlatformRoleConsistency extends Command
{
    protected $signature = 'dmims:fix-platform-role-consistency {--dry-run : List mismatches without changing anything}';

    protected $description = 'Correct any user whose is_platform_user flag disagrees with whether they hold a platform-tier role (Datamation Super Admin / Datamation Management).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $users = User::withoutGlobalScopes()->with('roles')->get();

        $mismatched = $users->filter(function (User $user) {
            $hasPlatformRole = $user->roles->pluck('name')
                ->intersect(UserResource::PLATFORM_ROLES)
                ->isNotEmpty();

            return $user->is_platform_user !== $hasPlatformRole;
        });

        if ($mismatched->isEmpty()) {
            $this->info('No is_platform_user/role mismatches found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->warn("{$mismatched->count()} user(s) have is_platform_user out of sync with their roles:");
        $this->table(
            ['ID', 'Email', 'Roles', 'is_platform_user (current)', 'customer_id', 'Will become'],
            $mismatched->map(fn (User $u) => [
                $u->id,
                $u->email,
                $u->roles->pluck('name')->implode(', ') ?: '(none)',
                $u->is_platform_user ? 'true' : 'false',
                $u->customer_id ?? '(null)',
                $u->is_platform_user ? 'false' : 'true',
            ])
        );

        if ($dryRun) {
            $this->info('Dry run — no changes made. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        foreach ($mismatched as $user) {
            UserResource::enforcePlatformRoleConsistency($user);
        }

        $this->info("Corrected {$mismatched->count()} user(s).");

        return self::SUCCESS;
    }
}
