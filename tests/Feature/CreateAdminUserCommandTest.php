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

    /**
     * Was "warns but still creates user" — changed after a security review
     * found that a role-less is_platform_user=true account is an invalid
     * state UserResource::enforcePlatformRoleConsistency() would later
     * silently demote (the moment anyone opened it in the admin UI and
     * saved), with no error visible to the operator. Fail closed instead:
     * never create the account until the role actually exists.
     */
    public function test_fails_without_creating_a_user_when_role_is_missing(): void
    {
        // No RolesAndPermissionsSeeder call — "Datamation Super Admin" doesn't exist yet.
        $this->artisan('dmims:create-admin', [
            'email' => 'root@example.com',
            '--password' => 'a-strong-password',
        ])
            ->expectsOutputToContain('does not exist yet')
            ->assertFailed();

        // assertFalse(User::where(...)->exists()) would be vacuously true
        // here regardless of whether the row was soft- or force-deleted —
        // User's SoftDeletes global scope already hides it either way.
        // assertDatabaseMissing checks the actual table, catching a
        // soft-delete regression that would silently orphan the row and
        // permanently block a retry of this command for the same email.
        $this->assertDatabaseMissing('users', ['email' => 'root@example.com']);

        // The actual recovery path: seed the role, retry, succeed.
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->artisan('dmims:create-admin', [
            'email' => 'root@example.com',
            '--password' => 'a-strong-password',
        ])->assertSuccessful();

        $this->assertTrue(User::where('email', 'root@example.com')->exists());
    }
}
