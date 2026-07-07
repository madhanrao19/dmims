<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\BoxResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Box;
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

class BoxResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = Box::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':document_tracking'];

    protected static bool $applyCustomerScope = true;

    // Boxes are a document-tracking concept (Document Tracking nav group, gated on
    // the document_tracking module), so they use the documents permission — not
    // "manage inventory". With the old value the Document Tracking User and Viewer
    // roles (which hold manage/view documents, not inventory) were locked out.
    protected static ?string $permission = 'manage documents';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Document Tracking';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['box_number', 'box_barcode'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::customerIdField(),
                Forms\Components\TextInput::make('box_barcode')->required()->maxLength(150),
                Forms\Components\TextInput::make('box_number')->required()->maxLength(100),
                Forms\Components\Select::make('current_location_id')
                    ->relationship('currentLocation', 'location_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('source_origin')->maxLength(255),
                Forms\Components\TextInput::make('capacity_limit')->numeric()->helperText('Maximum number of files this box can hold.'),
                Forms\Components\TextInput::make('current_file_count')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Derived automatically from file movements — not editable.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                        'moved_out' => 'Moved Out',
                        'archived' => 'Archived',
                        'damaged' => 'Damaged',
                        'missing' => 'Missing',
                    ])
                    ->default('active')
                    ->required(),
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
                Tables\Columns\TextColumn::make('box_number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('box_barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('physical_path')->label('Location')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'closed', 'archived' => 'gray',
                        'moved_out' => 'info',
                        'damaged', 'missing' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('capacity_percent')
                    ->label('Capacity')
                    ->state(fn (Box $record): string => $record->capacity_limit
                        ? "{$record->current_file_count}/{$record->capacity_limit} ({$record->capacity_percent}%)"
                        : "{$record->current_file_count} files")
                    ->badge()
                    ->color(fn (Box $record): string => match (true) {
                        $record->capacity_percent === null => 'gray',
                        $record->capacity_percent >= 100 => 'danger',
                        $record->capacity_percent >= 80 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->color(fn (string $state, Box $record): string => $record->tags->firstWhere('name', $state)?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('transferBox')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (Box $record): bool => $record->status !== 'moved_out')
                    ->schema([
                        Forms\Components\Select::make('to_location_id')->label('To location')
                            ->options(fn () => Location::query()->pluck('location_name', 'id')->all())->searchable()->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (Box $record, array $data): void {
                        app(DocumentMovementService::class)->transferBox($record, (int) $data['to_location_id'], $data);
                        Notification::make()->title('Box transferred')->success()->send();
                    }),
                Action::make('moveOutBox')
                    ->label('Move Out')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->visible(fn (Box $record): bool => $record->status !== 'moved_out')
                    ->schema([
                        Forms\Components\TextInput::make('destination')->label('External destination')->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (Box $record, array $data): void {
                        app(DocumentMovementService::class)->moveOutBox($record, $data['destination'], $data);
                        Notification::make()->title('Box moved out')->success()->send();
                    }),
                Action::make('returnBox')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (Box $record): bool => $record->status === 'moved_out')
                    ->schema([
                        Forms\Components\Select::make('to_location_id')->label('Return to location')
                            ->options(fn () => Location::query()->pluck('location_name', 'id')->all())->searchable()->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (Box $record, array $data): void {
                        app(DocumentMovementService::class)->returnBox($record, (int) $data['to_location_id'], $data);
                        Notification::make()->title('Box returned')->success()->send();
                    }),
                Action::make('timeline')
                    ->label('Timeline')
                    ->icon('heroicon-o-clock')
                    ->modalHeading(fn (Box $record): string => "Activity timeline — Box {$record->box_number}")
                    ->modalContent(fn (Box $record) => view('filament.activity-timeline', [
                        'entries' => app(MovementTimelineService::class)->forBox($record),
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
            'index' => Pages\ListBoxes::route('/'),
            'create' => Pages\CreateBox::route('/create'),
            'edit' => Pages\EditBox::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\BoxResource\Pages;

use App\Filament\Resources\BoxResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListBoxes extends ListRecords
{
    protected static string $resource = BoxResource::class;
}

class CreateBox extends CreateRecord
{
    protected static string $resource = BoxResource::class;
}

class EditBox extends EditRecord
{
    protected static string $resource = BoxResource::class;
}
