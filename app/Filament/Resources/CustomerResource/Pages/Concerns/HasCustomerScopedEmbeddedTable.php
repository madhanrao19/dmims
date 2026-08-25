<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Filament\Resources\BaseResource;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
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

    /**
     * Docs/CONFORMANCE_GAP_ANALYSIS.md security acceptance: "Customer 360
     * child forms must not accept an arbitrary browser-selected customer
     * ownership." The wrapped resource's own form() always includes a free
     * customer_id Select (any customer, platform-wide) — reused verbatim
     * here EXCEPT that field is replaced with a Hidden component fixed to
     * this page's own Customer, so it's never shown or editable. This also
     * keeps $get('customer_id') resolving correctly for any other field
     * that depends on it (e.g. CustomerModuleResource's per-customer
     * uniqueness rule), unlike simply removing the field outright.
     * mutateFormDataUsing() then force-overwrites the submitted value
     * server-side regardless, as defence in depth against a tampered
     * hidden-field submission.
     *
     * @param  Closure(array<string, mixed>): Model|null  $using  Override for
     *                                                            resources whose own create page routes through a service instead
     *                                                            of a plain model create (e.g. BillingRecordResource's
     *                                                            CreateBillingRecord delegates to BillingService::createInvoice()
     *                                                            to generate invoice_no/total_amount) — reuse that same service
     *                                                            call here rather than bypassing it with a bare Eloquent create().
     *                                                            $data already has customer_id forced by mutateFormDataUsing()
     *                                                            below by the time this closure runs.
     */
    protected function customerScopedCreateAction(string $label, ?Closure $using = null): CreateAction
    {
        $resource = static::sourceResource();
        $customer = $this->getRecord();

        $action = CreateAction::make()
            ->label($label)
            ->model($resource::getModel())
            ->authorize(fn (): bool => $resource::can('create'))
            ->schema(function (Schema $schema) use ($resource, $customer): Schema {
                $schema = $resource::form($schema);

                return $schema->components(array_map(
                    // getKey() defaults to an absolute, container-prefixed
                    // key (e.g. could be prefixed by a parent grid/group) —
                    // the plain field name only comes from getKey(false).
                    fn ($component) => $component->getKey(false) === 'customer_id'
                        ? Hidden::make('customer_id')->default($customer->getKey())
                        : $component,
                    $schema->getComponents(),
                ));
            })
            ->mutateFormDataUsing(function (array $data) use ($customer): array {
                $data['customer_id'] = $customer->getKey();

                return $data;
            });

        return $using ? $action->using($using) : $action;
    }
}
