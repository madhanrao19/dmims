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
        'manage barcode',
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
        'view audit logs',
        'view barcode',
    ];

    /**
     * Stricter than the matching `manage X` — some roles may create/update a
     * resource but not delete it (Security & Access Control Matrix §10, §6).
     * BaseResource::permissionFor() checks these for delete actions instead
     * of the resource's `manage X` permission, when the resource sets
     * $deletePermission.
     */
    public const DELETE_PERMISSIONS = [
        'delete users',
        'delete inventory',
    ];

    /**
     * Weaker than `manage X` — reaches the update action only, never
     * create/delete. BaseResource::$limitedUpdatePermission checks these as
     * an alternative to `manage X` for the update action specifically.
     * "update users limited" is Company Supervisor's "Update User: Limited"
     * (Security & Access Control Matrix §6) — profile/operational fields
     * only (name, phone, job_title, department_id, employee_id); identity,
     * security, and privilege fields (email, username, password, status,
     * roles, customer_id) stay locked even under this grant. See
     * UserResource::form()/EditUser::mutateFormDataBeforeSave().
     */
    public const LIMITED_UPDATE_PERMISSIONS = [
        'update users limited',
    ];

    public const PERMISSIONS = [
        ...self::MANAGE_PERMISSIONS, ...self::VIEW_PERMISSIONS,
        ...self::DELETE_PERMISSIONS, ...self::LIMITED_UPDATE_PERMISSIONS,
    ];

    /**
     * Role => permissions. '*' grants everything. "View-only" roles
     * (Management, Viewer) receive `view *` permissions only.
     */
    public const ROLE_PERMISSIONS = [
        'Datamation Super Admin' => '*',
        'Datamation Management' => [
            'view customers', 'view users', 'view subscriptions',
            'view licensing', 'view billing', 'view reports', 'view audit logs',
            'view barcode',
        ],
        'Company Admin' => [
            'manage users', 'delete inventory', 'manage inventory', 'manage documents',
            'manage barcode', 'view billing', 'view subscriptions', 'view licensing',
            'view reports', 'view customers', 'view audit logs',
        ],
        'Company Supervisor' => [
            'manage inventory', 'manage documents', 'manage barcode',
            'view billing', 'view subscriptions', 'view licensing', 'view reports',
            'view customers', 'view users', 'update users limited',
        ],
        'Stock Inventory User' => ['manage inventory', 'manage barcode', 'view reports'],
        'Document Tracking User' => ['manage documents', 'manage barcode', 'view reports'],
        'Viewer' => ['view inventory', 'view documents', 'view reports', 'view barcode'],
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
