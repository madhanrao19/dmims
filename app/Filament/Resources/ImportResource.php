<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportResource\Pages;
use App\Models\Import;
use App\Services\ImportService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ImportResource extends BaseResource
{
    protected static ?string $model = Import::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-on-square';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('import_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('import_type')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing', 'uploaded', 'validating', 'validated' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_rows')->label('Total')->sortable(),
                Tables\Columns\TextColumn::make('success_rows')->label('OK')->sortable(),
                Tables\Columns\TextColumn::make('failed_rows')->label('Failed')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'uploaded' => 'Uploaded',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                Action::make('newImport')
                    ->label('New Import')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Forms\Components\Select::make('import_type')
                            ->label('Data to import')
                            ->options(collect(array_keys(ImportService::importableTypes()))
                                ->mapWithKeys(fn (string $t) => [$t => str($t)->headline()->toString()])
                                ->all())
                            ->required()
                            ->live(),
                        Forms\Components\Placeholder::make('expected_columns')
                            ->label('Expected CSV columns')
                            ->content(function (Get $get): string {
                                $type = $get('import_type');
                                $types = ImportService::importableTypes();

                                return $type && isset($types[$type])
                                    ? implode(', ', $types[$type]['columns'])
                                    : 'Select a type to see the expected columns.';
                            }),
                        Forms\Components\FileUpload::make('file')
                            ->label('CSV file')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->disk('local')
                            ->directory('imports')
                            ->storeFileNamesIn('original_name')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $user = auth()->user();

                        $import = Import::create([
                            'customer_id' => $user->is_platform_user ? null : $user->customer_id,
                            'import_no' => 'IMP-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                            'import_type' => $data['import_type'],
                            'file_name' => $data['original_name'] ?? basename($data['file']),
                            'file_path' => $data['file'],
                            'status' => 'uploaded',
                            'uploaded_by' => $user->id,
                        ]);

                        try {
                            $import = app(ImportService::class)->process($import);
                            Notification::make()
                                ->title('Import finished')
                                ->body("{$import->success_rows} imported, {$import->failed_rows} failed of {$import->total_rows} rows.")
                                ->{$import->failed_rows > 0 ? 'warning' : 'success'}()
                                ->send();
                        } catch (\Throwable $e) {
                            $import->update(['status' => 'failed']);
                            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('viewRows')
                    ->label('Rows')
                    ->icon('heroicon-o-table-cells')
                    ->modalContent(fn (Import $record) => view('filament.import-rows', ['rows' => $record->rows()->orderBy('row_number')->limit(500)->get()]))
                    ->modalSubmitAction(false)
                    ->visible(fn (Import $record): bool => $record->rows()->exists()),
                Action::make('downloadErrors')
                    ->label('Errors')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->visible(fn (Import $record): bool => $record->failed_rows > 0)
                    ->action(fn (Import $record) => app(ImportService::class)->errorFileResponse($record)),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImports::route('/'),
        ];
    }
}

namespace App\Filament\Resources\ImportResource\Pages;

use App\Filament\Resources\ImportResource;
use Filament\Resources\Pages\ListRecords;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;
}
