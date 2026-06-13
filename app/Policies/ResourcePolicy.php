<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResourcePolicy
{
    use HandlesAuthorization;

    protected array $map = [
        'Customer' => 'manage customers',
        'User' => 'manage users',
        'Product' => 'manage inventory',
        'Category' => 'manage inventory',
        'Location' => 'manage inventory',
        'LocationType' => 'manage inventory',
        'StockMovement' => 'manage inventory',
        'BarcodeRegistry' => 'manage inventory',
        'Box' => 'manage inventory',
        'ProductLocationStock' => 'manage inventory',
        'DocumentFile' => 'manage documents',
        'DocumentType' => 'manage documents',
        'CustomerSubscription' => 'manage subscriptions',
        'SubscriptionPlan' => 'manage subscriptions',
        'CustomerModule' => 'manage subscriptions',
        'License' => 'manage licensing',
        'LicenseLog' => 'manage licensing',
        'Import' => 'manage settings',
        'Export' => 'manage settings',
        'Notification' => 'manage settings',
        'SupportAccessLog' => 'manage settings',
        'StockAdjustmentApproval' => 'manage inventory',
        'StockAlert' => 'manage inventory',
        'Setting' => 'manage settings',
        'AuditLog' => 'view reports',
        'Backup' => 'manage settings',
    ];

    protected function permissionFor($model): ?string
    {
        if (is_string($model)) {
            $class = class_basename($model);
        } elseif (is_object($model)) {
            $class = class_basename(get_class($model));
        } else {
            return null;
        }

        return $this->map[$class] ?? null;
    }

    protected function checkOwnership(User $user, $model): bool
    {
        if ($user->is_platform_user) {
            return true;
        }

        if (is_object($model) && property_exists($model, 'customer_id')) {
            // allow null customer (platform-owned) or matching customer
            return $model->customer_id === null || $model->customer_id == $user->customer_id;
        }

        // For collection / class-level checks, allow if user has the permission
        return true;
    }

    public function viewAny(User $user, $model = null): bool
    {
        if ($user->is_platform_user) {
            return true;
        }

        $permission = $this->permissionFor($model);

        return $permission ? $user->can($permission) : false;
    }

    public function view(User $user, $model): bool
    {
        if ($user->is_platform_user) {
            return true;
        }

        $permission = $this->permissionFor($model);

        return ($permission ? $user->can($permission) : false) && $this->checkOwnership($user, $model);
    }

    public function create(User $user, $model = null): bool
    {
        if ($user->is_platform_user) {
            return true;
        }

        $permission = $this->permissionFor($model);

        return $permission ? $user->can($permission) : false;
    }

    public function update(User $user, $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return $this->view($user, $model);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $this->view($user, $model);
    }

    public function restore(User $user, $model): bool
    {
        return $this->view($user, $model);
    }

    public function reorder(User $user): bool
    {
        return $user->is_platform_user || $user->can('manage inventory');
    }
}
