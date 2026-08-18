<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'dmims:create-admin
                            {email? : The administrator email address}
                            {--name=Administrator : Display name}
                            {--password= : Password (generated if omitted)}';

    protected $description = 'Create a platform administrator user.';

    private const SUPER_ADMIN_ROLE = 'Datamation Super Admin';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Administrator email');
        $password = $this->option('password') ?: Str::password(16);

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(8)]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'is_platform_user' => true,
            'status' => 'active',
        ]);

        // is_platform_user alone only grants read access (BaseResource::can());
        // every write action additionally requires a real permission, which
        // only comes from an assigned role. Without this, "platform admin"
        // silently means "read-only" with no error anywhere.
        if (Role::where('name', self::SUPER_ADMIN_ROLE)->exists()) {
            $user->assignRole(self::SUPER_ADMIN_ROLE);
        } else {
            $this->warn(
                'Role "'.self::SUPER_ADMIN_ROLE.'" does not exist yet — run '.
                '`php artisan db:seed --class=RolesAndPermissionsSeeder` first, then '.
                "assign it manually: php artisan tinker --execute=\"User::where('email','{$email}')->first()->assignRole('".self::SUPER_ADMIN_ROLE."')\""
            );
            $this->warn('Until then, this account can view but not create/edit/delete anything.');
        }

        $this->info("Platform admin created: {$user->email}");

        if (! $this->option('password')) {
            $this->warn("Generated password: {$password}");
            $this->warn('Store it securely and change it after first login.');
        }

        return self::SUCCESS;
    }
}
