<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_cleanly_and_sets_up_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(9, Permission::count());
        $this->assertEqualsCanonicalizing(['admin', 'manager', 'user'], Role::pluck('name')->all());

        // Non-platform access depends on these being populated.
        $this->assertGreaterThanOrEqual(1, Customer::count());
        $this->assertSame(2, Module::count());
        $this->assertGreaterThanOrEqual(1, CustomerSubscription::count());

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->can('manage inventory'));

        // Manager has operational permissions but not platform administration.
        $this->assertTrue(Role::findByName('manager')->hasPermissionTo('manage inventory'));
        $this->assertFalse(Role::findByName('manager')->hasPermissionTo('manage customers'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Customer::where('company_code', 'DEMO')->count());
        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertSame(9, Permission::count());
    }
}
