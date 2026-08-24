<?php

namespace App\Filament\Clusters\MyCompany\Pages\Concerns;

use App\Filament\Resources\BaseResource;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * TDD §7.3: "Do not duplicate business logic." Each My Company tab that
 * lists records reuses its source resource's own table()/getEloquentQuery()
 * verbatim — the same columns, filters, row actions and tenant scoping the
 * resource already defines and already has test coverage for — rather than
 * redefining them here. canAccess() likewise delegates to the resource's
 * own can('viewAny'), so a tab is only as visible as the resource it wraps
 * already decided a user may view (permission, module, license — all of it).
 *
 * Mirrors how Filament's own ListRecords page composes a table via
 * EmbeddedTable::make() in content().
 */
trait HasEmbeddedResourceTable
{
    /**
     * Override on each concrete page. A method (not a property) because PHP
     * forbids a using class from redeclaring a trait's typed property with
     * a different default value — see BelongsToCustomer for the same
     * pattern.
     *
     * @return class-string<BaseResource>
     */
    abstract protected static function sourceResource(): string;

    public static function canAccess(): bool
    {
        return static::sourceResource()::can('viewAny');
    }

    /**
     * Named to avoid colliding with Filament\Tables\Concerns\InteractsWithTable's
     * own table() — PHP forbids two traits used in the same class from
     * defining the same method without explicit conflict resolution. Each
     * concrete page defines its own table() delegating here.
     */
    protected function embeddedResourceTable(Table $table): Table
    {
        $resource = static::sourceResource();
        $table = $resource::table($table)->query($resource::getEloquentQuery());

        // Resources with no explicit row action (Users, Enabled Modules,
        // Subscription, Audit Logs) rely on their ListRecords page's own
        // click-to-row-edit fallback (Filament\Resources\Pages\ListRecords)
        // for navigation — a plain embedding Page doesn't get that fallback
        // automatically, so row clicks would silently do nothing. Reproduce
        // the same fallback here: view then edit, whichever the resource
        // has a page and permission for. A resource that already sets its
        // own recordUrl()/recordActions() (e.g. Billing's modal actions)
        // is untouched.
        if (! $table->hasCustomRecordUrl()) {
            $table->recordUrl(function (Model $record) use ($resource): ?string {
                foreach (['view', 'edit'] as $action) {
                    if (! $resource::hasPage($action) || ! $resource::{'can'.ucfirst($action)}($record)) {
                        continue;
                    }

                    return $resource::getUrl($action, ['record' => $record]);
                }

                return null;
            });
        }

        return $table;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    /**
     * Security review finding (24 August 2026): without this, a framework
     * default action (ViewAction/EditAction/etc.) with no explicit
     * ->authorize() call — e.g. BillingRecordResource's ViewAction/EditAction
     * — falls through Filament's own default of "allowed" on a plain Page,
     * because only Filament\Resources\Pages\Page (used by a resource's own
     * pages, not by an embedding cluster page) maps these to the resource's
     * authorization. Restore that mapping here, mirroring
     * Filament\Resources\Pages\Page::getDefaultActionAuthorizationResponse()
     * exactly, but fail closed (deny) for any action type not explicitly
     * mapped — unlike the framework version, which returns null (defaults
     * to allowed) for anything unrecognised.
     */
    public function getDefaultActionAuthorizationResponse(Action $action): ?Response
    {
        $resource = static::sourceResource();

        return match (true) {
            $action instanceof CreateAction => $resource::getCreateAuthorizationResponse(),
            $action instanceof DeleteAction => $resource::getDeleteAuthorizationResponse($action->getRecord()),
            $action instanceof EditAction => $resource::getEditAuthorizationResponse($action->getRecord()),
            $action instanceof ForceDeleteAction => $resource::getForceDeleteAuthorizationResponse($action->getRecord()),
            $action instanceof ReplicateAction => $resource::getReplicateAuthorizationResponse($action->getRecord()),
            $action instanceof RestoreAction => $resource::getRestoreAuthorizationResponse($action->getRecord()),
            $action instanceof ViewAction => $resource::getViewAuthorizationResponse($action->getRecord()),
            $action instanceof DeleteBulkAction => $resource::getDeleteAnyAuthorizationResponse(),
            $action instanceof ForceDeleteBulkAction => $resource::getForceDeleteAnyAuthorizationResponse(),
            $action instanceof RestoreBulkAction => $resource::getRestoreAnyAuthorizationResponse(),
            default => Response::deny(),
        };
    }

    /**
     * Companion to the authorization fix above: without this, ViewAction/
     * EditAction open a modal with no fields at all (Filament\Tables\Concerns
     * has no default schema resolver either) — mirrors
     * Filament\Resources\Pages\ListRecords::getDefaultActionSchemaResolver().
     */
    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        $resource = static::sourceResource();

        return match (true) {
            $action instanceof CreateAction, $action instanceof EditAction => fn (Schema $schema): Schema => $resource::form($schema->hasCustomColumns() ? $schema : $schema->columns(2)),
            $action instanceof ViewAction => fn (Schema $schema): Schema => $resource::infolist($resource::form($schema->hasCustomColumns() ? $schema : $schema->columns(2))),
            default => null,
        };
    }
}
