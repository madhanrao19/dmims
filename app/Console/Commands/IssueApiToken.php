<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * ponytail: CLI-issued tokens are enough for the first integration partners.
 * Add a self-service Filament token-management UI if/when non-technical
 * users need to issue their own tokens.
 */
class IssueApiToken extends Command
{
    protected $signature = 'dmims:issue-api-token {user : User ID or email} {name=api : Token name}';

    protected $description = 'Issue a Sanctum API token for a user, for use against routes/api.php.';

    public function handle(): int
    {
        $identifier = $this->argument('user');

        $user = is_numeric($identifier)
            ? User::find($identifier)
            : User::where('email', $identifier)->first();

        if (! $user) {
            $this->error("No user found matching [{$identifier}].");

            return self::FAILURE;
        }

        $token = $user->createToken($this->argument('name'));

        $this->info("Token for {$user->email}:");
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
