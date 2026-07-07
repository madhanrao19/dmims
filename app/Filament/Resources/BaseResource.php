<?php

namespace App\Filament\Resources;

use App\Services\AccessControlService;
use App\Services\ModuleAccessService;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

abstract class BaseResource extends Resource
{
    protected static bool $applyCustomerScope = false;

    protected static ?string $permission = null;

    /**
     * A `customer_id` select for tenant-owned resources. Platform users pick any
     * customer; tenant users never see the field (nor the full customer roster it
     * would enumerate) and the owning customer is forced server-side by the
     * BelongsToCustomer `saving` hook. Keeps `customer_id` derived from the
     * authenticated user, never trusted from the request.
     */
    protected static function customerIdField(bool $required = true): Select
    {
        return Select::make('customer_id')
            ->relationship('customer', 'company_name', function (Builder $query): Builder {
                $user = auth()->user();

                if ($user && ! $user->is_platform_user && $user->customer_id) {
                    $query->where('id', $user->customer_id);
                }

                return $query;
            })
            ->searchable()
            ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user)
            ->dehydrated(fn (): bool => (bool) auth()->user()?->is_platform_user)
            ->required(fn (): bool => $required && (bool) auth()->user()?->is_platform_user);
    }

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

    public static function can(string|UnitEnum $action, ?Model $record = null): bool
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

        if (! filled(static::$permission)) {
            return false;
        }

        $isWrite = in_array($action, static::WRITE_ACTIONS, true);

        if ($isWrite) {
            // Writes require the manage permission and a non-view-only license.
            if (! $user->can(static::$permission)) {
                return false;
            }

            return app(AccessControlService::class)->getEffectiveAccessMode($user->customer_id)
                !== AccessControlService::MODE_VIEW_ONLY;
        }

        // Reads are allowed with either the manage or the view permission
        // (role-based view-only access per the Security & Access Control Matrix).
        return $user->can(static::$permission) || $user->can(static::viewPermission());
    }

    /**
     * The read-only permission corresponding to this resource's `$permission`
     * (e.g. "manage inventory" -> "view inventory"). Resources already gated on
     * a "view *" permission map to themselves.
     */
    protected static function viewPermission(): string
    {
        return str_starts_with((string) static::$permission, 'manage ')
            ? 'view '.substr(static::$permission, strlen('manage '))
            : (string) static::$permission;
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

        // show nav when the user can either manage or view the resource
        if (filled(static::$permission)
            && ! $user->can(static::$permission)
            && ! $user->can(static::viewPermission())) {
            return false;
        }

        // hide navigation when the resource's module is disabled for this customer
        return static::moduleEnabledForUser($user);
    }
}
