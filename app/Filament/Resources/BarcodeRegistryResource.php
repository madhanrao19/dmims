<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BarcodeRegistryResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\AuditLog;
use App\Models\BarcodeRegistry;
use App\Services\BarcodeService;
use App\Services\ModuleAccessService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component as LivewireComponent;

/**
 * Barcode Center (production-readiness roadmap #1): generate, batch
 * generate, batch print, reprint, history, and lost/damaged replacement —
 * all in one place rather than scattered per-record actions.
 */
class BarcodeRegistryResource extends BaseResource
{
    protected static ?string $model = BarcodeRegistry::class;

    // Business Rules §10: Barcode Center is the Barcode Scanning module's
    // feature (generate/register/reprint barcodes), not Stock Inventory —
    // a customer can have Barcode Scanning enabled without Stock Inventory.
    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':barcode_scanning'];

    protected static bool $applyCustomerScope = true;

    // Security & Access Control Matrix §12: View Registry is granted to
    // every role, including Document Tracking User (who previously had no
    // inventory permission at all and was fully blocked) and Viewer.
    protected static ?string $permission = 'manage barcode';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Barcodes';

    protected static string|\UnitEnum|null $navigationGroup = 'Shared Services';

    protected static ?int $navigationSort = 1;

    /** Every barcode type this resource knows about, with the module/permission that gates it. */
    private const TYPE_META = [
        'product' => ['label' => 'Product', 'module' => 'stock_inventory', 'permission_area' => 'inventory'],
        'location' => ['label' => 'Location', 'module' => 'stock_inventory', 'permission_area' => 'inventory'],
        'box' => ['label' => 'Box', 'module' => 'document_tracking', 'permission_area' => 'documents'],
        'document_file' => ['label' => 'Document File', 'module' => 'document_tracking', 'permission_area' => 'documents'],
    ];

    /**
     * Record types selectable for Batch Generate/Reserve Labels — limited to
     * this customer's enabled modules and the acting user's own permissions
     * (Business Rules: a customer without Stock Inventory enabled has no
     * business generating Product/Location barcodes, even though the
     * barcode_scanning module itself is separate and may be enabled alone).
     * Platform users administer across every tenant/type, so see the full set.
     */
    private static function availableTypeOptions(): array
    {
        $user = auth()->user();

        if (! $user || $user->is_platform_user) {
            return array_map(fn (array $meta) => $meta['label'], self::TYPE_META);
        }

        $moduleService = app(ModuleAccessService::class);
        $customerId = $user->customer_id;

        return collect(self::TYPE_META)
            ->filter(function (array $meta) use ($user, $customerId, $moduleService): bool {
                $hasPermission = $user->can("manage {$meta['permission_area']}") || $user->can("view {$meta['permission_area']}");

                return $hasPermission && $customerId && $moduleService->isModuleEnabled($customerId, $meta['module']);
            })
            ->map(fn (array $meta) => $meta['label'])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('barcode')->required()->maxLength(150)->disabled(),
                Forms\Components\Select::make('barcode_type')
                    ->options([
                        'product' => 'Product',
                        'location' => 'Location',
                        'box' => 'Box',
                        'document_file' => 'Document File',
                    ])
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'retired' => 'Retired',
                        'unused' => 'Unused (reserved, unclaimed)',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('barcode')->sortable()->searchable()->fontFamily('mono'),
                Tables\Columns\TextColumn::make('barcode_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('reference_table')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'retired' => 'danger',
                        'unused' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('printed_count')->label('Printed')->sortable(),
                Tables\Columns\TextColumn::make('last_scanned_at')->dateTime()->sortable(),
            ])
            // The reference project's "Search Barcode" is this list's own
            // search bar + filters, not a separate screen — see the
            // batchGenerate replacement note below.
            ->searchPlaceholder('Search barcode number...')
            ->filters([
                Tables\Filters\SelectFilter::make('barcode_type')
                    ->label('Barcode type')
                    ->options(fn () => self::availableTypeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'retired' => 'Retired',
                        'unused' => 'Unused (reserved, unclaimed)',
                    ]),
            ])
            ->headerActions([
                // Pre-prints/reserves labels for records that don't exist
                // yet — these are claimed later by BarcodeService::claim()
                // when a matching record is created with the same barcode
                // value (typed manually, or carried over via a ?barcode=
                // query param on that resource's own Create form). Labeled
                // "Batch Generate" (was "Reserve Labels") — the previous,
                // separate "Batch Generate" action (assigning barcodes to
                // existing un-barcoded DB rows) was removed; this is now
                // the only batch barcode action.
                Action::make('batchGenerate')
                    ->label('Batch Generate')
                    ->icon('heroicon-o-ticket')
                    ->authorize(fn (): bool => static::can('create'))
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'company_name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => auth()->user()?->is_platform_user ? null : auth()->user()?->customer_id)
                            ->required()
                            ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user),
                        Forms\Components\Select::make('type')
                            ->label('Record type')
                            ->options(fn () => self::availableTypeOptions())
                            ->required(),
                        Forms\Components\TextInput::make('count')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(200)
                            ->default(10)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $customerId = $data['customer_id'] ?? auth()->user()?->customer_id;
                        $reserved = app(BarcodeService::class)->reserve($customerId, $data['type'], (int) $data['count']);

                        Notification::make()
                            ->title($reserved->count().' barcode(s) reserved')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview / Print')
                    ->icon('heroicon-o-eye')
                    // 'view', not 'update' — see HasBarcodeAction's own
                    // authorize() comment: printing doesn't modify the
                    // record, so a view-only role can still use it.
                    ->authorize(fn (BarcodeRegistry $record): bool => static::can('view', $record))
                    ->modalHeading('Barcode label')
                    ->schema([
                        Forms\Components\Select::make('size')
                            ->label('Label size')
                            ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                            ->default('medium')
                            ->live(),
                        Forms\Components\TextInput::make('copies')
                            ->label('Copies')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live(),
                        Forms\Components\Toggle::make('show_name')->label('Show Name')->default(true)->live(),
                        Forms\Components\Toggle::make('show_barcode')->label('Show Barcode')->default(true)->live(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    // incrementPrinted() runs here, not in ->modalContent()
                    // below — that closure's `Get $get` genuinely tracks the
                    // label-size/copies Selects' ->live() updates (see
                    // HasBarcodeAction's own top-of-file comment for why it
                    // must be `Get $get`, not `array $data`), so incrementing
                    // there would count every live re-render as a print, not
                    // just the one open. mountUsing() runs exactly once, when
                    // the modal opens — same reasoning is why "Copies" is
                    // read from the field's default here rather than a
                    // later, live-updated value (printing is fully
                    // client-side, no server round trip to hang a precise
                    // count off of).
                    ->mountUsing(function (Schema $schema, BarcodeRegistry $record): void {
                        $schema->fill();
                        app(BarcodeService::class)->incrementPrinted($record, (int) ($schema->getState()['copies'] ?? 1));
                    })
                    ->modalContent(function (BarcodeRegistry $record, LivewireComponent&HasSchemas $livewire) {
                        $data = static::liveActionData($livewire);

                        return view('filament.barcode-label', [
                            'barcode' => $record->barcode,
                            'type' => $record->barcode_type,
                            'title' => app(BarcodeService::class)->resolveTitle($record),
                            'size' => $data['size'] ?? 'medium',
                            'copies' => max(1, (int) ($data['copies'] ?? 1)),
                            'showName' => (bool) ($data['show_name'] ?? true),
                            'showBarcodeText' => (bool) ($data['show_barcode'] ?? true),
                        ]);
                    }),
                Action::make('replace')
                    ->label('Lost/Damaged')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (BarcodeRegistry $record): bool => $record->status === 'active')
                    ->authorize(fn (BarcodeRegistry $record): bool => static::can('update', $record))
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2)
                            ->helperText('Why is this barcode being retired? Kept on the record\'s audit log.'),
                    ])
                    ->modalDescription('Retires this barcode and issues a new one for the same record. The old code is kept in history.')
                    ->action(function (BarcodeRegistry $record, array $data): void {
                        $new = app(BarcodeService::class)->replace($record);
                        AuditLog::create([
                            'customer_id' => $record->customer_id,
                            'user_id' => auth()->id(),
                            'module' => 'barcode_registry',
                            'action' => 'replace',
                            'auditable_type' => BarcodeRegistry::class,
                            'auditable_id' => $new->id,
                            'old_values' => ['barcode' => $record->barcode],
                            'new_values' => ['barcode' => $new->barcode, 'reason' => $data['reason']],
                        ]);
                        Notification::make()
                            ->title('Barcode replaced')
                            ->body("New barcode: {$new->barcode}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('batchPrint')
                    ->label('Batch Print')
                    ->icon('heroicon-o-printer')
                    // See preview()'s own authorize() comment.
                    ->authorize(fn (): bool => static::can('view'))
                    ->schema([
                        Forms\Components\Select::make('size')
                            ->label('Label size')
                            ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                            ->default('small')
                            ->live(),
                        Forms\Components\TextInput::make('copies')
                            ->label('Copies (each)')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live(),
                        Forms\Components\Toggle::make('show_name')->label('Show Name')->default(true)->live(),
                        Forms\Components\Toggle::make('show_barcode')->label('Show Barcode')->default(true)->live(),
                    ])
                    ->modalHeading('Batch print preview')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    // See the 'preview' action above for why this runs in
                    // ->mountUsing() (once, on open) rather than
                    // ->modalContent() (re-evaluated on every render).
                    ->mountUsing(function (Schema $schema, Collection $records): void {
                        /** @var Collection<int, BarcodeRegistry> $records */
                        $schema->fill();
                        $copies = (int) ($schema->getState()['copies'] ?? 1);
                        $records->each(fn (BarcodeRegistry $record) => app(BarcodeService::class)->incrementPrinted($record, $copies));
                    })
                    ->modalContent(function (Collection $records, LivewireComponent&HasSchemas $livewire) {
                        /** @var Collection<int, BarcodeRegistry> $records */
                        $data = static::liveActionData($livewire);
                        $barcodeService = app(BarcodeService::class);

                        return view('filament.batch-barcode-labels', [
                            'registries' => $records->map(fn (BarcodeRegistry $record): array => [
                                'registry' => $record,
                                'title' => $barcodeService->resolveTitle($record),
                            ]),
                            'size' => $data['size'] ?? 'small',
                            'copies' => max(1, (int) ($data['copies'] ?? 1)),
                            'showName' => (bool) ($data['show_name'] ?? true),
                            'showBarcodeText' => (bool) ($data['show_barcode'] ?? true),
                        ]);
                    }),
            ])
            ->defaultSort('barcode');
    }

    /**
     * See HasBarcodeAction's top-of-file comment for why modalContent()
     * must read this way rather than `array $data` or `Get $get`.
     *
     * @param  LivewireComponent&HasSchemas  $livewire
     * @return array<string, mixed>
     */
    protected static function liveActionData(LivewireComponent $livewire): array
    {
        return $livewire->getSchema('mountedActionSchema0')?->getState() ?? [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBarcodeRegistries::route('/'),
            'edit' => Pages\EditBarcodeRegistry::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\BarcodeRegistryResource\Pages;

use App\Filament\Resources\BarcodeRegistryResource;
use App\Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListBarcodeRegistries extends ListRecords
{
    protected static string $resource = BarcodeRegistryResource::class;
}

class EditBarcodeRegistry extends EditRecord
{
    protected static string $resource = BarcodeRegistryResource::class;
}
