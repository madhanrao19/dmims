<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\LocationResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Location;
use App\Models\LocationType;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

class LocationResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = Location::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static bool $applyCustomerScope = true;

    // Platform Customer 360 Design Review, item 10 (extended 25 Aug 2026):
    // platform users reach this via Customer 360's Locations tab instead of
    // a standalone top-level entry that mixed every customer's locations
    // into one list. Tenant users are unaffected by this flag — they keep
    // their own existing top-level "Locations" nav (same as Categories/
    // Products/Stock Movements), which was already correctly scoped to
    // their own company via BelongsToCustomer.
    protected static bool $consolidatedViaCustomer360 = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Locations';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['location_code', 'location_name', 'barcode'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload()
                    // Hidden fields still feed Get::get('customer_id') below
                    // (location_code/barcode uniqueness scoping) — without a
                    // default a tenant user's hidden field resolves to null,
                    // silently disabling that scoping and letting a raw DB
                    // constraint violation through instead of an inline error.
                    ->default(fn (): ?int => auth()->user()?->is_platform_user ? null : auth()->user()?->customer_id)
                    ->required()
                    // BelongsToCustomer forces this to the tenant's own
                    // company regardless of what's submitted, so showing it
                    // to a tenant is only ever a confusing single-option
                    // picker — same precedent as BillingRecordResource's
                    // own customer_id field.
                    ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Location')
                    ->relationship('parent', 'location_name')
                    ->getOptionLabelFromRecordUsing(fn (Location $record): string => $record->ancestry_path)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('location_type_id')
                    ->label('Location Type')
                    ->relationship('locationType', 'type_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('location_code')->maxLength(100)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages(['unique' => 'This location code is already in use for the selected customer.']),
                Forms\Components\TextInput::make('location_name')->maxLength(255),
                Forms\Components\TextInput::make('barcode')->maxLength(100)
                    // Pre-fills from a ?barcode= query param, if present
                    // (e.g. a reserved-but-unclaimed barcode from Barcode
                    // Center's "Reserve Labels" — see BarcodeService::claim()).
                    ->default(fn (string $operation): ?string => $operation === 'create' ? request()->query('barcode') : null)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_id', $get('customer_id')),
                    )
                    ->validationMessages(['unique' => 'This barcode is already in use for the selected customer.']),
                Forms\Components\Toggle::make('can_store_stock')->default(true),
                Forms\Components\Toggle::make('can_store_boxes')->default(true),
                Forms\Components\TextInput::make('box_capacity')->numeric()->helperText('Maximum number of boxes this shelf/rack can hold (optional).'),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('location_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('locationType.type_name')->label('Type')->sortable(),
                Tables\Columns\TextColumn::make('parent.location_name')->label('Parent')->sortable(),
                Tables\Columns\TextColumn::make('box_capacity')
                    ->label('Box capacity')
                    ->state(fn (Location $record): string => $record->box_capacity
                        ? "{$record->boxes_used_count}/{$record->box_capacity} boxes ({$record->box_capacity_percent}%)"
                        : "{$record->boxes_used_count} boxes")
                    ->badge()
                    ->color(fn (Location $record): string => match (true) {
                        $record->box_capacity_percent === null => 'gray',
                        $record->box_capacity_percent >= 100 => 'danger',
                        $record->box_capacity_percent >= 80 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->recordActions([
                ActionGroup::make([
                    // A plain EditAction navigates to the standalone
                    // /locations/{id}/edit page, which is jarring when this
                    // table is embedded in Customer 360's Locations tab —
                    // edit in place instead, mirroring the in-modal action
                    // pattern already used by DocumentFileResource's
                    // Transfer/Return.
                    Action::make('edit')
                        ->label('Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->authorize(fn (Location $record): bool => static::can('update', $record))
                        ->fillForm(fn (Location $record): array => $record->toArray())
                        ->schema(fn (Schema $schema): Schema => static::form($schema))
                        ->action(function (Location $record, array $data): void {
                            // Every other edit path forces customer_id back to
                            // the actor's own tenant server-side (see
                            // ForcesOwnCustomerId) — this in-modal action is a
                            // plain $record->update($data), not an EditRecord
                            // page, so it would otherwise be the one edit path
                            // in the app that skips that second layer.
                            $user = auth()->user();
                            if ($user && ! $user->is_platform_user && $user->customer_id) {
                                $data['customer_id'] = $user->customer_id;
                            }

                            $record->update($data);
                            Notification::make()->title('Location updated')->success()->send();
                        }),
                    static::barcodeAction(),
                    DeleteAction::make()
                        ->authorize(fn (Location $record): bool => static::can('delete', $record))
                        ->failureNotificationTitle('Cannot delete')
                        ->failureNotificationMessage('This location still has boxes, sub-locations, or stock linked to it and cannot be deleted while those exist.')
                        ->action(function (DeleteAction $action): void {
                            try {
                                $result = $action->process(static fn (Model $record): ?bool => $record->delete());
                            } catch (QueryException $e) {
                                if ($e->getCode() !== '23000') {
                                    throw $e;
                                }

                                $action->failure();

                                return;
                            }

                            if (! $result) {
                                $action->failure();

                                return;
                            }

                            $action->success();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::bulkBarcodeAction(),
                    DeleteBulkAction::make()
                        ->action(fn (Collection $records) => static::deleteSelectedWithReport($records)),
                ]),
            ])
            ->headerActions([
                static::createChainAction(),
                static::batchGenerateAction(),
            ])
            ->defaultSort('location_name');
    }

    /**
     * Bulk-creates a whole nested-parent chain of locations (e.g. Warehouse
     * > Building > Rack) in one submit, instead of one at a time. Each row
     * threads into the next row's parent_id — reuses an existing sibling
     * (matched by location_code under the same parent) instead of
     * duplicating it, so re-running the builder to extend a chain is safe.
     * Wrapped in one DB::transaction() so a mid-chain collision leaves
     * nothing partially created. Every row goes through Location::create()
     * (never a raw insert/upsert), which keeps the parent-cycle/cross-tenant
     * guard in Location::booted() active.
     *
     * $lockedCustomerId: set when embedded in Customer 360's Locations tab
     * (CustomerResource\Pages\Locations) — same lock-not-hide-then-force
     * pattern as HasCustomerScopedEmbeddedTable::customerScopedCreateAction(),
     * so this bulk action can't be used to create locations under a
     * different, browser-selected customer.
     *
     * Labeled "Add Location" (not "Location Chain Builder") — this is now
     * the single create entry point for Locations, replacing what used to
     * be two separate buttons (a plain single-row create + this chain
     * builder). "Starting Parent" left blank and a chain of exactly one row
     * behaves exactly like the old plain single-location create; a deeper
     * chain builds the full hierarchy in one submit. No separate simple
     * create action remains.
     */
    public static function createChainAction(?int $lockedCustomerId = null): Action
    {
        return Action::make('createChain')
            ->label('Add Location')
            ->icon('heroicon-o-plus')
            ->authorize(fn (): bool => static::can('create'))
            ->slideOver()
            ->schema([
                $lockedCustomerId ? Forms\Components\Hidden::make('customer_id')->default($lockedCustomerId) : static::customerIdField(),
                Forms\Components\Select::make('starting_parent_id')
                    ->label('Starting Parent (optional)')
                    ->options(fn (): array => Location::ancestryPathMap())
                    ->searchable(),
                Forms\Components\Repeater::make('levels')
                    ->label('Location Chain')
                    ->schema([
                        Forms\Components\Select::make('location_type_id')
                            ->label('Type')
                            ->options(fn (): array => LocationType::where('status', 'active')->orderBy('sort_order')->pluck('type_name', 'id')->all())
                            ->required(),
                        Forms\Components\TextInput::make('location_code')->label('Code')->required()->maxLength(100),
                        Forms\Components\TextInput::make('location_name')->label('Name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('barcode')->label('Barcode (optional)')->maxLength(100),
                    ])
                    ->columns(4)
                    ->addActionLabel('+ Add Level')
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->minItems(1)
                    ->default([
                        ['location_type_id' => null, 'location_code' => '', 'location_name' => '', 'barcode' => null],
                        ['location_type_id' => null, 'location_code' => '', 'location_name' => '', 'barcode' => null],
                    ])
                    ->helperText('Add one row per level, top to bottom — e.g. Warehouse -> Building -> Rack.'),
            ])
            ->action(function (array $data) use ($lockedCustomerId): void {
                $customerId = $lockedCustomerId ?? $data['customer_id'] ?? auth()->user()?->customer_id;

                try {
                    DB::transaction(function () use ($data, $customerId): void {
                        $parentId = $data['starting_parent_id'] ?? null;

                        foreach ($data['levels'] as $level) {
                            $existing = Location::where('customer_id', $customerId)
                                ->where('parent_id', $parentId)
                                ->where('location_code', $level['location_code'])
                                ->first();

                            $node = $existing ?? Location::create([
                                'customer_id' => $customerId,
                                'parent_id' => $parentId,
                                'location_type_id' => $level['location_type_id'],
                                'location_code' => $level['location_code'],
                                'location_name' => $level['location_name'],
                                'barcode' => $level['barcode'] ?: null,
                                'status' => 'active',
                            ]);

                            $parentId = $node->id;
                        }
                    });
                } catch (QueryException $e) {
                    Notification::make()
                        ->title('Chain not created')
                        ->body('A location code or barcode in this chain is already in use. Nothing was saved.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()->title('Location chain created')->success()->send();
            });
    }

    /**
     * Creates a flat range of sibling locations under one parent (e.g.
     * SHELF-01..SHELF-10) from a code/name/barcode prefix + start/end
     * number. A duplicate code or barcode is skipped (not an error) and
     * counted, matching the pattern; the whole range still runs inside one
     * DB::transaction() so an unexpected mid-loop failure rolls back
     * cleanly instead of leaving a partial batch.
     *
     * $lockedCustomerId: see createChainAction()'s doc-comment — same lock.
     */
    public static function batchGenerateAction(?int $lockedCustomerId = null): Action
    {
        return Action::make('batchGenerate')
            ->label('Batch Generate')
            ->icon('heroicon-o-squares-plus')
            ->authorize(fn (): bool => static::can('create'))
            ->schema([
                $lockedCustomerId ? Forms\Components\Hidden::make('customer_id')->default($lockedCustomerId) : static::customerIdField(),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Location')
                    ->options(fn (): array => Location::ancestryPathMap())
                    ->searchable()
                    ->helperText('Leave blank to create top-level locations.'),
                Forms\Components\Select::make('location_type_id')
                    ->label('Type')
                    ->options(fn (): array => LocationType::where('status', 'active')->orderBy('sort_order')->pluck('type_name', 'id')->all()),
                Forms\Components\TextInput::make('code_prefix')->label('Code Prefix')->required()->maxLength(80),
                Forms\Components\TextInput::make('name_prefix')->label('Name Prefix')->required()->maxLength(200)
                    ->helperText('e.g. "SHELF-" generates SHELF-01, SHELF-02, ...'),
                Forms\Components\TextInput::make('barcode_prefix')->label('Barcode Prefix (optional)')->maxLength(80),
                Forms\Components\TextInput::make('start_number')->numeric()->required()->default(1),
                Forms\Components\TextInput::make('end_number')->numeric()->required()->default(10)
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                            $start = (int) $get('start_number');
                            if ((int) $value < $start) {
                                $fail("End number must be greater than or equal to start number ({$start}).");
                            }
                            if ((int) $value - $start > 500) {
                                $fail('Batch is limited to 500 locations at a time.');
                            }
                        },
                    ]),
            ])
            ->action(function (array $data) use ($lockedCustomerId): void {
                $customerId = $lockedCustomerId ?? $data['customer_id'] ?? auth()->user()?->customer_id;
                $created = 0;
                $skipped = 0;

                DB::transaction(function () use ($data, $customerId, &$created, &$skipped): void {
                    for ($n = (int) $data['start_number']; $n <= (int) $data['end_number']; $n++) {
                        $suffix = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                        $code = $data['code_prefix'].$suffix;
                        $barcode = filled($data['barcode_prefix'] ?? null) ? $data['barcode_prefix'].$suffix : null;

                        $exists = Location::where('customer_id', $customerId)
                            ->where(fn ($q) => $q->where('location_code', $code)->when($barcode, fn ($q2) => $q2->orWhere('barcode', $barcode)))
                            ->exists();

                        if ($exists) {
                            $skipped++;

                            continue;
                        }

                        Location::create([
                            'customer_id' => $customerId,
                            'parent_id' => $data['parent_id'] ?? null,
                            'location_type_id' => $data['location_type_id'] ?? null,
                            'location_code' => $code,
                            'location_name' => $data['name_prefix'].$suffix,
                            'barcode' => $barcode,
                            'status' => 'active',
                        ]);
                        $created++;
                    }
                });

                Notification::make()
                    ->title('Batch Generation Complete')
                    ->body("Created {$created}. Skipped {$skipped} duplicate(s).")
                    ->success()
                    ->send();
            });
    }

    /**
     * customer_id field shared by the two bulk-create actions above — same
     * platform-user-only visibility/default pattern as form()'s own field.
     */
    protected static function customerIdField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('customer_id')
            ->label('Customer')
            ->relationship('customer', 'company_name')
            ->searchable()
            ->preload()
            ->default(fn (): ?int => auth()->user()?->is_platform_user ? null : auth()->user()?->customer_id)
            ->required()
            ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user);
    }

    /**
     * Location detail page tab bar — Overview / Audit Log, so "review a
     * shelf's audit history" (the final step of the original demo script)
     * has a page to land on, matching the Box/Document File pattern.
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewLocation::class,
            Pages\AuditLog::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
            'audit-log' => Pages\AuditLog::route('/{record}/audit-log'),
        ];
    }
}

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;
use App\Services\BarcodeService;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    /**
     * Suppress the app-wide base ListRecords' default page-navigating
     * "Create" button — table()'s own headerActions() already supplies
     * "Add Location" (createChainAction()) and "Batch Generate", so a third
     * plain-create button here would be a redundant, differently-behaved
     * duplicate. The standalone /locations/create route/page itself is left
     * in place (unused today, harmless to keep) rather than deleted.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    /**
     * Captured at mount() — see CreateDocumentFile::$fromBarcodeScan for why
     * afterCreate() can't just re-read request()->filled('barcode') itself.
     */
    public bool $fromBarcodeScan = false;

    public function mount(): void
    {
        parent::mount();

        $this->fromBarcodeScan = request()->filled('barcode');
    }

    /** Attach a reserved-but-unclaimed barcode if one was pre-filled — see
     *  BarcodeService::claim(). No-op for a manually-typed barcode. */
    protected function afterCreate(): void
    {
        // See DocumentFileResource/BoxResource's afterCreate() for the same
        // registerExisting() fallback and why it's scoped to the scan-to-
        // create flow only (?barcode= present) rather than every manual entry.
        if (! app(BarcodeService::class)->claim($this->record) && $this->fromBarcodeScan) {
            app(BarcodeService::class)->registerExisting($this->record);
        }
    }
}

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;
}
