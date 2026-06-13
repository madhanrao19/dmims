<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the roles and permissions the application's access control depends
 * on. Safe to run on a production install — it contains no demo data and is
 * idempotent.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $permissions = [
            'manage customers',
            'manage users',
            'manage inventory',
            'manage documents',
            'manage subscriptions',
            'manage licensing',
            'manage settings',
            'manage modules',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Admin gets everything; manager runs day-to-day operations; user is read-mostly.
        $adminRole->syncPermissions($permissions);
        $managerRole->syncPermissions(['manage inventory', 'manage documents', 'view reports']);
        $userRole->syncPermissions(['view reports']);
    }
}
