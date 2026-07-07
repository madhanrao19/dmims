<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\DocumentFileResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Box;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Services\DocumentMovementService;
use App\Services\MovementTimelineService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentFileResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = DocumentFile::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':document_tracking'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage documents';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Documents';

    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return ['file_barcode', 'file_reference_no', 'title', 'owner_name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::customerIdField(),
                Forms\Components\TextInput::make('file_barcode')->required()->maxLength(150),
                Forms\Components\TextInput::make('file_reference_no')->maxLength(150),
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\Select::make('document_type_id')
                    ->relationship('documentType', 'type_name')
                    ->searchable(),
                Forms\Components\Select::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable(),
                Forms\Components\TextInput::make('owner_name')->maxLength(255),
                Forms\Components\Select::make('current_box_id')
                    ->relationship('currentBox', 'box_number')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('current_status')
                    ->options([
                        'active' => 'Active',
                        'transferred' => 'Transferred',
                        'moved_out' => 'Moved Out',
                        'archived' => 'Archived',
                        'missing' => 'Missing',
                        'damaged' => 'Damaged',
                        'closed' => 'Closed',
                    ])
                    ->default('active')->required(),
                Forms\Components\TextInput::make('source_origin')->maxLength(255),
                Forms\Components\TextInput::make('destination')->maxLength(255),
                Forms\Components\DatePicker::make('received_date'),
                Forms\Components\DatePicker::make('archived_date'),
                Forms\Components\Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\ColorPicker::make('color')->default('#6b7280'),
                    ]),
                Forms\Components\Textarea::make('remarks')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('title')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('owner_name')->label('Owner')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('currentBox.box_number')->label('Box')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('physical_path')->label('Location')->toggleable(),
                Tables\Columns\TextColumn::make('current_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'transferred' => 'info',
                        'moved_out' => 'warning',
                        'archived', 'closed' => 'gray',
                        'missing', 'damaged' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->color(fn (string $state, DocumentFile $record): string => $record->tags->firstWhere('name', $state)?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due back')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (DocumentFile $record): string => $record->is_overdue ? 'danger' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue returns')
                    ->query(fn ($query) => $query->where('current_status', 'moved_out')->whereNotNull('due_date')->whereDate('due_date', '<', now())),
                Tables\Filters\SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('current_status')
                    ->options([
                        'active' => 'Active',
                        'transferred' => 'Transferred',
                        'moved_out' => 'Moved Out',
                        'archived' => 'Archived',
                        'missing' => 'Missing',
                        'damaged' => 'Damaged',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Department'),
                Tables\Filters\SelectFilter::make('current_box_id')
                    ->relationship('currentBox', 'box_number')
                    ->label('Box')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('warehouse')
                    ->label('Warehouse/Shelf')
                    ->options(fn () => Location::query()->pluck('location_name', 'id')->all())
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn ($q, $locationId) => $q->whereHas('currentBox', fn ($bq) => $bq->where('current_location_id', $locationId))
                        );
                    }),
                Tables\Filters\Filter::make('received_date')
                    ->schema([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('received_date', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('received_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('transferFile')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (DocumentFile $record): bool => $record->current_status !== 'moved_out')
                    ->schema([
                        Forms\Components\Select::make('to_box_id')->label('To box')
                            ->options(fn () => Box::query()->pluck('box_number', 'id')->all())->searchable()->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (DocumentFile $record, array $data): void {
                        app(DocumentMovementService::class)->transferFile($record, (int) $data['to_box_id'], $data);
                        Notification::make()->title('File transferred')->success()->send();
                    }),
                Action::make('moveOutFile')
                    ->label('Move Out')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->visible(fn (DocumentFile $record): bool => $record->current_status !== 'moved_out')
                    ->schema([
                        Forms\Components\TextInput::make('destination')->label('External destination')->required(),
                        Forms\Components\TextInput::make('borrowed_by')->label('Borrowed by'),
                        Forms\Components\DatePicker::make('due_date')->label('Due back'),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (DocumentFile $record, array $data): void {
                        app(DocumentMovementService::class)->moveOutFile($record, $data['destination'], $data);
                        Notification::make()->title('File moved out')->success()->send();
                    }),
                Action::make('returnFile')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (DocumentFile $record): bool => $record->current_status === 'moved_out')
                    ->schema([
                        Forms\Components\Select::make('to_box_id')->label('Return to box')
                            ->options(fn () => Box::query()->pluck('box_number', 'id')->all())->searchable()->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (DocumentFile $record, array $data): void {
                        app(DocumentMovementService::class)->returnFile($record, (int) $data['to_box_id'], $data);
                        Notification::make()->title('File returned')->success()->send();
                    }),
                Action::make('timeline')
                    ->label('Timeline')
                    ->icon('heroicon-o-clock')
                    ->modalHeading(fn (DocumentFile $record): string => "Activity timeline — {$record->title}")
                    ->modalContent(fn (DocumentFile $record) => view('filament.activity-timeline', [
                        'entries' => app(MovementTimelineService::class)->forDocumentFile($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                EditAction::make(),
                static::barcodeAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentFiles::route('/'),
            'create' => Pages\CreateDocumentFile::route('/create'),
            'edit' => Pages\EditDocumentFile::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\DocumentFileResource\Pages;

use App\Filament\Resources\DocumentFileResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListDocumentFiles extends ListRecords
{
    protected static string $resource = DocumentFileResource::class;
}

class CreateDocumentFile extends CreateRecord
{
    protected static string $resource = DocumentFileResource::class;
}

class EditDocumentFile extends EditRecord
{
    protected static string $resource = DocumentFileResource::class;
}
