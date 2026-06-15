<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the roles and permissions the application's access control depends on,
 * matching the Security & Access Control Matrix and TDD §5.
 *
 * Each functional area has a `manage X` (full CRUD) and a `view X` (read-only)
 * permission; BaseResource::can() allows read actions on either, and write
 * actions only on `manage X`. This gives the matrix's view-only roles
 * (Management, Viewer) genuine read access rather than no access.
 *
 * Safe to run on a production install — no demo data, idempotent.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public const MANAGE_PERMISSIONS = [
        'manage customers',
        'manage users',
        'manage inventory',
        'manage documents',
        'manage subscriptions',
        'manage licensing',
        'manage billing',
        'manage settings',
        'manage modules',
    ];

    public const VIEW_PERMISSIONS = [
        'view customers',
        'view users',
        'view inventory',
        'view documents',
        'view subscriptions',
        'view licensing',
        'view billing',
        'view settings',
        'view modules',
        'view reports',
    ];

    public const PERMISSIONS = [...self::MANAGE_PERMISSIONS, ...self::VIEW_PERMISSIONS];

    /**
     * Role => permissions. '*' grants everything. "View-only" roles
     * (Management, Viewer) receive `view *` permissions only.
     */
    public const ROLE_PERMISSIONS = [
        'Datamation Super Admin' => '*',
        'Datamation Management' => [
            'view customers', 'view users', 'view subscriptions',
            'view licensing', 'view billing', 'view reports',
        ],
        'Company Admin' => [
            'manage users', 'manage inventory', 'manage documents', 'manage billing',
            'view subscriptions', 'view licensing', 'view reports',
        ],
        'Company Supervisor' => [
            'manage inventory', 'manage documents',
            'view billing', 'view subscriptions', 'view licensing', 'view reports',
        ],
        'Stock Inventory User' => ['manage inventory', 'view reports'],
        'Document Tracking User' => ['manage documents', 'view reports'],
        'Viewer' => ['view inventory', 'view documents', 'view reports'],
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
