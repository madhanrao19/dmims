<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BarcodeRegistryResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\BarcodeRegistry;
use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\ModuleAccessService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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

    /** Types selectable for batch generation, mapped to their model. */
    private const BATCH_TYPES = [
        'product' => Product::class,
        'location' => Location::class,
        'box' => Box::class,
        'document_file' => DocumentFile::class,
    ];

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
                Action::make('batchGenerate')
                    ->label('Batch Generate')
                    ->icon('heroicon-o-squares-plus')
                    ->authorize(fn (): bool => static::can('create'))
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Record type')
                            ->options(fn () => self::availableTypeOptions())
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('record_ids')
                            ->label('Records without a barcode yet')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function (Get $get) {
                                $type = $get('type');
                                if (! $type || ! isset(self::BATCH_TYPES[$type])) {
                                    return [];
                                }

                                [$column] = self::unbarcodedColumn($type);

                                return self::BATCH_TYPES[$type]::query()
                                    ->whereNull($column)
                                    ->limit(200)
                                    ->pluck(self::labelColumn($type), 'id');
                            }),
                    ])
                    ->action(function (array $data): void {
                        $modelClass = self::BATCH_TYPES[$data['type']];
                        $records = $modelClass::query()->whereIn('id', $data['record_ids'])->get();

                        foreach ($records as $record) {
                            app(BarcodeService::class)->registerFor($record);
                        }

                        Notification::make()
                            ->title('Barcodes generated')
                            ->body(count($records).' record(s) now have a barcode.')
                            ->success()
                            ->send();
                    }),
                // Pre-prints/reserves labels for records that don't exist
                // yet — unlike Batch Generate above (which only barcodes
                // existing DB rows), these are claimed later by
                // BarcodeService::claim() when a matching record is created
                // (see the Scan Center's "unused barcode → create form"
                // redirect).
                Action::make('reserve')
                    ->label('Reserve Labels')
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
                    ->authorize(fn (BarcodeRegistry $record): bool => static::can('update', $record))
                    ->modalHeading('Barcode label')
                    ->schema([
                        Forms\Components\Select::make('size')
                            ->label('Label size')
                            ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                            ->default('medium')
                            ->live(),
                    ])
                    ->modalContent(fn (BarcodeRegistry $record, array $data) => view('filament.barcode-label', [
                        'barcode' => $record->barcode,
                        'type' => $record->barcode_type,
                        'size' => $data['size'] ?? 'medium',
                    ]))
                    ->modalSubmitActionLabel('Mark as printed')
                    ->action(function (BarcodeRegistry $record): void {
                        app(BarcodeService::class)->incrementPrinted($record);
                        Notification::make()->title("Reprinted: {$record->barcode}")->success()->send();
                    }),
                Action::make('replace')
                    ->label('Lost/Damaged')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (BarcodeRegistry $record): bool => $record->status === 'active')
                    ->authorize(fn (BarcodeRegistry $record): bool => static::can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Retires this barcode and issues a new one for the same record. The old code is kept in history.')
                    ->action(function (BarcodeRegistry $record): void {
                        $new = app(BarcodeService::class)->replace($record);
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
                    ->authorize(fn (): bool => static::can('update'))
                    ->schema([
                        Forms\Components\Select::make('size')
                            ->label('Label size')
                            ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                            ->default('small'),
                    ])
                    ->modalHeading('Batch print preview')
                    ->modalContent(fn (Collection $records, array $data) => view('filament.batch-barcode-labels', [
                        'registries' => $records,
                        'size' => $data['size'] ?? 'small',
                    ]))
                    ->action(function (Collection $records): void {
                        /** @var Collection<int, BarcodeRegistry> $records */
                        $records->each(fn (BarcodeRegistry $record) => app(BarcodeService::class)->incrementPrinted($record));
                        Notification::make()->title('Batch marked as printed')->success()->send();
                    }),
            ])
            ->defaultSort('barcode');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBarcodeRegistries::route('/'),
            'edit' => Pages\EditBarcodeRegistry::route('/{record}/edit'),
        ];
    }

    /**
     * @return array{0: string} the model column that is null until a barcode is generated
     */
    private static function unbarcodedColumn(string $type): array
    {
        return match ($type) {
            'box' => ['box_barcode'],
            'document_file' => ['file_barcode'],
            default => ['barcode'],
        };
    }

    private static function labelColumn(string $type): string
    {
        return match ($type) {
            'product' => 'sku',
            'location' => 'location_name',
            'box' => 'box_number',
            'document_file' => 'title',
            default => 'id',
        };
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
