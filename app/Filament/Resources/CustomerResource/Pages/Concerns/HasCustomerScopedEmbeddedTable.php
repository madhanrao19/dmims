<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Filament\Resources\BaseResource;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform Customer 360's counterpart to
 * App\Filament\Clusters\MyCompany\Pages\Concerns\HasEmbeddedResourceTable.
 *
 * KEY DIFFERENCE: My Company's acting user IS the tenant, so
 * $resource::getEloquentQuery() is already scoped to their own customer for
 * free. Here the acting user is a platform user viewing an ARBITRARY
 * selected Customer, so that query is unfiltered — this trait adds the
 * missing ->where('customer_id', ...) constraint explicitly. Dropping this
 * line is the single most likely way this feature could defeat tenant
 * isolation (Customer A's tab must never show Customer B's rows).
 */
trait HasCustomerScopedEmbeddedTable
{
    /** @return class-string<BaseResource> */
    abstract protected static function sourceResource(): string;

    /**
     * Unlike ViewRecord/EditRecord (which define this themselves), a plain
     * Filament\Resources\Pages\Page pulling in InteractsWithRecord has
     * nothing that actually calls resolveRecord() for the {record} route
     * parameter — without this, $this->record stays unset and getRecord()
     * 404s. InteractsWithRecord's own mountCanAuthorizeAccess()/
     * hydrateCanAuthorizeAccess() (Livewire trait hooks) still run
     * independently and enforce canAccess() once the record is set here.
     */
    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    // Base Page::content() has nothing to render by default — mirrors
    // HasEmbeddedResourceTable::content() exactly.
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    protected function customerScopedResourceTable(Table $table): Table
    {
        $resource = static::sourceResource();
        $customer = $this->getRecord();

        $table = $resource::table($table)
            ->query($resource::getEloquentQuery()->where('customer_id', $customer->getKey()));

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
}
