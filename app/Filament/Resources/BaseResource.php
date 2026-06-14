<?php

namespace App\Filament\Resources;

use App\Services\AccessControlService;
use App\Services\ModuleAccessService;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseResource extends Resource
{
    protected static bool $applyCustomerScope = false;

    protected static ?string $permission = null;

    /** Actions that modify data; blocked when the license is view-only. */
    protected const WRITE_ACTIONS = [
        'create', 'update', 'delete', 'deleteAny',
        'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny',
        'reorder', 'replicate',
    ];

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! static::$applyCustomerScope) {
            return $query;
        }

        $user = auth()->user();

        if ($user && ! $user->is_platform_user && $user->customer_id) {
            return $query->where(function (Builder $query) use ($user) {
                $query->where('customer_id', $user->customer_id)
                    ->orWhereNull('customer_id');
            });
        }

        return $query;
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        if (static::shouldSkipAuthorization()) {
            return true;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_platform_user) {
            return true;
        }

        // Enforce module gating on actual access, not just navigation visibility.
        // Without this, a user with the permission could reach a disabled
        // module's pages by navigating directly to the URL.
        if (! static::moduleEnabledForUser($user)) {
            return false;
        }

        if (! filled(static::$permission) || ! $user->can(static::$permission)) {
            return false;
        }

        // License view-only mode (SAD layer 5): permitted to read, not to write.
        if (in_array($action, static::WRITE_ACTIONS, true)
            && app(AccessControlService::class)->getEffectiveAccessMode($user->customer_id) === AccessControlService::MODE_VIEW_ONLY) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether every module required by this resource's route
     * middleware is enabled for the given user's customer.
     */
    protected static function moduleEnabledForUser($user): bool
    {
        if (! property_exists(static::class, 'routeMiddleware') || empty(static::$routeMiddleware)) {
            return true;
        }

        $middleware = static::$routeMiddleware;
        $items = is_array($middleware) ? $middleware : [$middleware];

        foreach ($items as $m) {
            if (is_string($m) && str_contains($m, ':')) {
                [$mw, $arg] = explode(':', $m, 2);
                if (str_contains($mw, 'EnsureModuleEnabled')) {
                    $service = new ModuleAccessService;
                    if ($user->customer_id && ! $service->isModuleEnabled($user->customer_id, $arg)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true; // allow navigation to register for unauthenticated contexts
        }

        if ($user->is_platform_user) {
            return true;
        }

        // check permission if defined
        if (filled(static::$permission) && ! $user->can(static::$permission)) {
            return false;
        }

        // hide navigation when the resource's module is disabled for this customer
        return static::moduleEnabledForUser($user);
    }
}
