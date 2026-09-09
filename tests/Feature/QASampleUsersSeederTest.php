<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\QASampleUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression coverage for the staging-gate fix: the seeder previously only
 * ran on 'local'/'testing', so deploy-ubuntu-24.sh's --seed-qa-users flag
 * (documented to work with --env staging) silently seeded nothing on a real
 * staging box. Also covers the staging-only password requirement added
 * alongside that fix, so a public staging site never ships the well-known
 * "password" default.
 */
class QASampleUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_refuses_on_production(): void
    {
        app()->instance('env', 'production');

        $this->artisan('db:seed', ['--class' => QASampleUsersSeeder::class, '--force' => true]);

        $this->assertDatabaseMissing('users', ['email' => 'qa-companyadmin@example.com']);
    }

    public function test_seeder_works_on_local_with_default_password(): void
    {
        app()->instance('env', 'local');

        $this->artisan('db:seed', ['--class' => QASampleUsersSeeder::class, '--force' => true]);

        $user = User::where('email', 'qa-companyadmin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_seeder_on_staging_requires_dmims_qa_password_env_var(): void
    {
        app()->instance('env', 'staging');
        putenv('DMIMS_QA_PASSWORD');

        $this->expectException(RuntimeException::class);

        (new QASampleUsersSeeder)->run();
    }

    public function test_seeder_on_staging_uses_the_env_password(): void
    {
        app()->instance('env', 'staging');
        putenv('DMIMS_QA_PASSWORD=a-strong-staging-password');

        (new QASampleUsersSeeder)->run();

        $user = User::where('email', 'qa-companyadmin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('a-strong-staging-password', $user->password));

        putenv('DMIMS_QA_PASSWORD');
    }
}
