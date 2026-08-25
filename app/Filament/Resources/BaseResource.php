<?php

namespace App\Filament\Resources;

use App\Services\AccessControlService;
use App\Services\ModuleAccessService;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

abstract class BaseResource extends Resource
{
    protected static bool $applyCustomerScope = false;

    /**
     * Security & Access Control Matrix §3.3 (TENANT_WITH_GLOBAL_DEFAULTS):
     * when true, a customer_id IS NULL record is also visible/reachable
     * alongside the tenant's own rows. Default false means TENANT_STRICT
     * (§3.2) — exact customer_id match only. Only set true where the matrix
     * explicitly approves shared global defaults (e.g. Document Types).
     */
    protected static bool $includeGlobalCustomerDefaults = false;

    /**
     * Security & Access Control Matrix §3.1 (PLATFORM_ONLY): when true, no
     * customer user may access this resource at all — regardless of any
     * permission they hold (e.g. Subscription Plans, License Management).
     * `view X`/`manage X` on these resources may still be granted to
     * customer roles for other purposes (dashboards, own-record summaries),
     * so permission checks alone can't express this restriction.
     */
    protected static bool $platformOnly = false;

    /**
     * TDD §7.3 / Security & Access Control Matrix §5: when true, a
     * non-platform user reaches this resource's data through the My Company
     * cluster (App\Filament\Clusters\MyCompany) instead of a standalone
     * top-level navigation entry. The resource's own routes, pages and
     * can()/getEloquentQuery() are unchanged and still fully functional
     * (My Company's tabs embed this resource's own table() directly, and
     * row actions like Edit still link to this resource's own edit route) —
     * only the duplicate sidebar entry is hidden. Platform users are
     * unaffected: they keep using the resource directly for cross-tenant
     * administration.
     */
    protected static bool $customerFacingViaMyCompany = false;

    /**
     * docs/CONFORMANCE_GAP_ANALYSIS.md, Platform Customer 360 Design Review,
     * item 10: when true, a PLATFORM user also reaches this resource's data
     * through Customer 360 (App\Filament\Resources\CustomerResource's
     * record sub-navigation) instead of a standalone top-level navigation
     * entry — hiding it for PLATFORM users only. Whether non-platform users
     * also lose their own standalone entry is controlled independently by
     * $customerFacingViaMyCompany: most resources set both flags together
     * (e.g. UserResource), but LocationResource sets only this one — tenant
     * users keep their existing standalone "Locations" nav (already
     * correctly scoped to their own company), only platform users are
     * redirected to Customer 360. The resource's own routes, pages and
     * can()/getEloquentQuery() are unchanged and still fully functional —
     * Customer 360's embedded tables link straight to them, and its "Add X"
     * actions create through the same resource — only the duplicate
     * top-level sidebar entry is hidden. Deliberately NOT set on
     * AuditLogResource: the approved target keeps "Platform Audit Logs" as
     * its own separate, cross-customer view.
     */
    protected static bool $consolidatedViaCustomer360 = false;

    protected static ?string $permission = null;

    /**
     * Optional stricter permission required for delete actions specifically.
     * The Security & Access Control Matrix sometimes allows a role to
     * create/update a resource but not delete it (e.g. Company Supervisor can
     * manage inventory but not delete products) — $permission alone can't
     * express that distinction. Falls back to $permission when null.
     */
    protected static ?string $deletePermission = null;

    /**
     * Key into AccessControlService::getEffectiveLimits() (e.g. 'max_users',
     * 'max_products') that caps how many rows a tenant may create for this
     * resource. Business Rules §7's "Limit Rule" requires this — it existed
     * as a computed value but was never actually enforced anywhere. Left
     * null for resources with no documented limit.
     */
    protected static ?string $usageLimitKey = null;

    /**
     * Optional weaker alternative to $permission for the update action only
     * — a role that may edit an existing record but not create or delete
     * one (e.g. Company Supervisor's "Update User: Limited" per Security &
     * Access Control Matrix §6). Holding this permission is enough to reach
     * the update action; it does NOT imply $permission, so create/delete
     * stay gated on $permission/$deletePermission as normal. Which fields
     * are actually editable under this weaker grant is up to the resource's
     * own form/mutateFormDataBeforeSave — this only controls whether the
     * update action is reachable at all.
     */
    protected static ?string $limitedUpdatePermission = null;

    /** Actions that modify data; blocked when the license is view-only. */
    protected const WRITE_ACTIONS = [
        'create', 'update', 'delete', 'deleteAny',
        'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny',
        'reorder', 'replicate',
    ];

    protected const DELETE_ACTIONS = ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny'];

    /** The permission that gates $action, honouring $deletePermission for deletes. */
    protected static function permissionFor(string|UnitEnum $action): string
    {
        if (in_array($action, static::DELETE_ACTIONS, true) && filled(static::$deletePermission)) {
            return static::$deletePermission;
        }

        return static::$permission;
    }

    /**
     * Whether creating another row would exceed the tenant's subscription
     * limit for this resource (Business Rules §7's "Limit Rule": prevent
     * new records once the limit is reached, existing records stay
     * accessible). No-op for resources without $usageLimitKey set.
     */
    protected static function usageLimitReached($user): bool
    {
        if (! static::$usageLimitKey || ! $user->customer_id) {
            return false;
        }

        $limit = app(AccessControlService::class)
            ->getEffectiveLimits($user->customer_id)[static::$usageLimitKey] ?? null;

        if ($limit === null) {
            return false;
        }

        $current = static::getModel()::withoutGlobalScopes()
            ->where('customer_id', $user->customer_id)
            ->count();

        return $current >= $limit;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        // Defence in depth alongside can()/shouldRegisterNavigation(): a
        // platform-only resource (Security & Access Control Matrix §3.1)
        // returns no rows at all for a non-platform user, independent of
        // $applyCustomerScope — protects any future relation manager/select
        // query against this resource that might bypass can().
        if (static::$platformOnly && $user && ! $user->is_platform_user) {
            return $query->whereRaw('1 = 0');
        }

        if (! static::$applyCustomerScope) {
            return $query;
        }

        if ($user && ! $user->is_platform_user) {
            // Fail closed for a non-platform user with no customer_id (a
            // data-integrity defect that should never log in — see
            // AccessControlService::canLogin()) rather than falling through
            // to an unscoped, cross-tenant query.
            if (! $user->customer_id) {
                return $query->whereRaw('1 = 0');
            }

            if (! static::$includeGlobalCustomerDefaults) {
                return $query->where('customer_id', $user->customer_id);
            }

            return $query->where(function (Builder $query) use ($user) {
                $query->where('customer_id', $user->customer_id)
                    ->orWhereNull('customer_id');
            });
        }

        return $query;
    }

    /**
     * Filament v5 authorises pages and actions through this method (mapped to
     * Gate policies), NOT through can() — so without this override the layered
     * access-control engine below never runs for panel requests. Route all
     * panel authorisation back through can().
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return static::can($action, $record) ? Response::allow() : Response::deny();
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

        if (static::$platformOnly && ! $user->is_platform_user) {
            return false;
        }

        // A non-platform user with no customer_id is a data-integrity defect
        // (should never log in — see AccessControlService::canLogin()); fail
        // closed rather than let the tenant checks below be silently skipped.
        if (! $user->is_platform_user && ! $user->customer_id) {
            return false;
        }

        // Tenant isolation, defence in depth: never authorise a record owned
        // by another customer (query scoping already hides them; this guards
        // direct-ID access paths too). Null customer_id = platform-owned,
        // and is only readable when the resource explicitly opts into
        // TENANT_WITH_GLOBAL_DEFAULTS (Security & Access Control Matrix §3.3)
        // — that opt-in is READ-only: a shared/global default record must
        // never be editable or deletable by a tenant user, or one tenant's
        // "manage" role could rename/delete a record every other tenant
        // relies on (BelongsToCustomer's creating/updating hooks would then
        // silently re-own it to that tenant).
        if ($record && ! $user->is_platform_user
            && array_key_exists('customer_id', $record->getAttributes())) {
            $recordCustomerId = $record->getAttribute('customer_id');

            if ($recordCustomerId === null) {
                $isWriteAction = in_array($action, static::WRITE_ACTIONS, true);

                if (! static::$includeGlobalCustomerDefaults || $isWriteAction) {
                    return false;
                }
            }

            if ($recordCustomerId !== null && (int) $recordCustomerId !== (int) $user->customer_id) {
                return false;
            }
        }

        if ($user->is_platform_user) {
            // Platform users have platform-wide read scope and skip the
            // module/license layers (those gate customers, not Datamation),
            // but writes still require the manage permission: the Security &
            // Access Control Matrix makes Datamation Management view-only.
            if (! filled(static::$permission)) {
                return true;
            }

            if (in_array($action, static::WRITE_ACTIONS, true)) {
                return $user->can(static::permissionFor($action));
            }

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
            // Writes require the manage permission (or the stricter delete
            // permission, when set) and a non-view-only license. Update
            // additionally accepts the weaker $limitedUpdatePermission —
            // a role that may edit but not create/delete (e.g. Company
            // Supervisor's "Update User: Limited").
            $hasPermission = $user->can(static::permissionFor($action))
                || ($action === 'update' && filled(static::$limitedUpdatePermission) && $user->can(static::$limitedUpdatePermission));

            if (! $hasPermission) {
                return false;
            }

            if ($action === 'create' && static::usageLimitReached($user)) {
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
            if (str_contains($m, ':')) {
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
            // Customer 360 consolidation hides nav for platform users only —
            // e.g. LocationResource consolidates for platform users while
            // tenant users keep their own separate standalone nav (checked
            // further below via $customerFacingViaMyCompany instead), so this
            // must not short-circuit before the platform-user branch.
            return ! static::$consolidatedViaCustomer360;
        }

        if (static::$platformOnly) {
            return false;
        }

        if (static::$customerFacingViaMyCompany) {
            return false;
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

    /**
     * Validation rule for a free-text field that must be valid JSON when
     * filled (e.g. license/subscription module-gating textareas), but is
     * allowed to be left blank.
     *
     * Wrapped in a zero-arg closure: every call site passes this straight to
     * ->rule(), and Filament's evaluate() dependency-injects any closure
     * given there by parameter name — it has no way to know a
     * (string $attribute, ...) closure is meant as a raw Laravel validation
     * callback instead, and throws "[$attribute] was unresolvable." The
     * outer closure takes no parameters, so evaluate() calls it with no
     * arguments and gets the real rule closure back untouched.
     */
    protected static function jsonRule(): \Closure
    {
        return fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }

            json_decode($value);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('The :attribute must be valid JSON.');
            }
        };
    }

    /**
     * A Textarea bound to a model attribute cast as 'array' (e.g.
     * enabled_modules/allowed_reports on CustomerSubscription/License) must
     * never receive the admin's raw JSON string as-is: Eloquent's array cast
     * setter assumes a string value is already meant literally and
     * json_encodes it again, so a valid ["a","b"] typed into the field was
     * silently stored double-encoded — read back later as the original
     * string instead of an array. Anything that then iterates the "array"
     * (e.g. CustomerSubscriptionObserver::syncEnabledModules()'s whereIn())
     * hit a hard TypeError instead of a validation error. Decode the typed
     * JSON into a real array before it ever reaches the model, and
     * re-encode the stored array back to text when an existing record is
     * loaded for editing.
     */
    protected static function jsonTextareaDehydrate(): \Closure
    {
        return function (?string $state) {
            return blank($state) ? null : json_decode($state, true);
        };
    }

    protected static function jsonTextareaHydrate(): \Closure
    {
        return function ($component, mixed $state): void {
            if (is_array($state)) {
                $component->state(json_encode($state));
            }
        };
    }
}
