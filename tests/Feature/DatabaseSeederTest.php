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

        $this->assertSame(19, Permission::count());
        $this->assertEqualsCanonicalizing([
            'Datamation Super Admin',
            'Datamation Management',
            'Company Admin',
            'Company Supervisor',
            'Stock Inventory User',
            'Document Tracking User',
            'Viewer',
        ], Role::pluck('name')->all());

        // Non-platform access depends on these being populated. The dictionary
        // defines six modules (stock_inventory, document_tracking, barcode_*,
        // reports, billing_view).
        $this->assertGreaterThanOrEqual(1, Customer::count());
        $this->assertSame(6, Module::count());
        $this->assertGreaterThanOrEqual(1, CustomerSubscription::count());

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('Datamation Super Admin'));
        $this->assertTrue($admin->can('manage inventory'));

        // Stock Inventory User has inventory access only; not customer admin.
        $this->assertTrue(Role::findByName('Stock Inventory User')->hasPermissionTo('manage inventory'));
        $this->assertFalse(Role::findByName('Stock Inventory User')->hasPermissionTo('manage customers'));

        // Management is read-only (reporting access, no management permissions).
        $this->assertTrue(Role::findByName('Datamation Management')->hasPermissionTo('view reports'));
        $this->assertFalse(Role::findByName('Datamation Management')->hasPermissionTo('manage inventory'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Customer::where('company_code', 'DEMO')->count());
        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertSame(19, Permission::count());
    }
}
