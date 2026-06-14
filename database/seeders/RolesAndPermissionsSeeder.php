<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the roles and permissions the application's access control depends on,
 * matching the Security & Access Control Matrix and TDD §5. Safe to run on a
 * production install — it contains no demo data and is idempotent.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Role => permissions. Datamation Super Admin is also flagged
     * is_platform_user and bypasses scoping; the explicit grant keeps the role
     * meaningful on its own. "Management" and "Viewer" are read-oriented and
     * receive only reporting access.
     */
    public const ROLE_PERMISSIONS = [
        'Datamation Super Admin' => '*',
        'Datamation Management' => ['view reports'],
        'Company Admin' => ['manage users', 'manage inventory', 'manage documents', 'manage billing', 'view reports'],
        'Company Supervisor' => ['manage inventory', 'manage documents', 'view reports'],
        'Stock Inventory User' => ['manage inventory'],
        'Document Tracking User' => ['manage documents'],
        'Viewer' => ['view reports'],
    ];

    public const PERMISSIONS = [
        'manage customers',
        'manage users',
        'manage inventory',
        'manage documents',
        'manage subscriptions',
        'manage licensing',
        'manage billing',
        'manage settings',
        'manage modules',
        'view reports',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions === '*' ? self::PERMISSIONS : $permissions);
        }
    }
}
