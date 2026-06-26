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

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':stock_inventory'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Barcode Center';

    protected static string|\UnitEnum|null $navigationGroup = 'Shared Services';

    protected static ?int $navigationSort = 1;

    /** Types selectable for batch generation, mapped to their model. */
    private const BATCH_TYPES = [
        'product' => Product::class,
        'location' => Location::class,
        'box' => Box::class,
        'document_file' => DocumentFile::class,
    ];

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
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('printed_count')->label('Printed')->sortable(),
                Tables\Columns\TextColumn::make('last_scanned_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('barcode_type')
                    ->options([
                        'product' => 'Product',
                        'location' => 'Location',
                        'box' => 'Box',
                        'document_file' => 'Document File',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired']),
            ])
            ->headerActions([
                Action::make('batchGenerate')
                    ->label('Batch Generate')
                    ->icon('heroicon-o-squares-plus')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Record type')
                            ->options([
                                'product' => 'Product',
                                'location' => 'Location',
                                'box' => 'Box',
                                'document_file' => 'Document File',
                            ])
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('record_ids')
                            ->label('Records without a barcode yet')
                            ->multiple()
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
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview / Print')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Barcode label')
                    ->schema([
                        Forms\Components\Select::make('size')
                            ->label('Label size')
                            ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                            ->default('medium')
                            ->live(),
                    ])
                    ->modalContent(fn (BarcodeRegistry $record, Get $get) => view('filament.barcode-label', [
                        'barcode' => $record->barcode,
                        'type' => $record->barcode_type,
                        'size' => $get('size') ?? 'medium',
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
                    ->schema([
                        Forms\Components\Select::make('size')
                            ->label('Label size')
                            ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                            ->default('small'),
                    ])
                    ->modalHeading('Batch print preview')
                    ->modalContent(fn (Collection $records, Get $get) => view('filament.batch-barcode-labels', [
                        'registries' => $records,
                        'size' => $get('size') ?? 'small',
                    ]))
                    ->action(function (Collection $records): void {
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
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListBarcodeRegistries extends ListRecords
{
    protected static string $resource = BarcodeRegistryResource::class;
}

class EditBarcodeRegistry extends EditRecord
{
    protected static string $resource = BarcodeRegistryResource::class;
}
