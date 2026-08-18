<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_admin_can_actually_write_not_just_read(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->artisan('dmims:create-admin', [
            'email' => 'root@example.com',
            '--password' => 'a-strong-password',
        ])->assertSuccessful();

        $user = User::where('email', 'root@example.com')->firstOrFail();

        $this->assertTrue($user->is_platform_user);
        $this->assertTrue(
            $user->can('manage users'),
            'A freshly created platform admin must be able to write, not just read — '.
            'is_platform_user alone only grants read access (see BaseResource::can()).'
        );
    }

    public function test_warns_but_still_creates_user_when_role_is_missing(): void
    {
        // No RolesAndPermissionsSeeder call — "Datamation Super Admin" doesn't exist yet.
        $this->artisan('dmims:create-admin', [
            'email' => 'root@example.com',
            '--password' => 'a-strong-password',
        ])
            ->expectsOutputToContain('does not exist yet')
            ->assertSuccessful();

        $user = User::where('email', 'root@example.com')->firstOrFail();

        $this->assertTrue($user->is_platform_user);
        $this->assertFalse($user->can('manage users'));
    }
}
