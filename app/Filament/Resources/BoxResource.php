<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasBarcodeAction;
use App\Filament\Resources\BoxResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\Box;
use App\Models\Location;
use App\Services\DocumentMovementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class BoxResource extends BaseResource
{
    use HasBarcodeAction;

    protected static ?string $model = Box::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':document_tracking'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage inventory';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Document Tracking';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('box_barcode')->required()->maxLength(150),
                Forms\Components\TextInput::make('box_number')->required()->maxLength(100),
                Forms\Components\Select::make('current_location_id')
                    ->relationship('currentLocation', 'location_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('source_origin')->maxLength(255),
                Forms\Components\TextInput::make('capacity_limit')->numeric(),
                Forms\Components\TextInput::make('current_file_count')->numeric()->default(0),
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
                Forms\Components\Textarea::make('remarks')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('box_number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('box_barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('currentLocation.location_name')->label('Location')->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('transferBox')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (Box $record): bool => $record->status !== 'moved_out')
                    ->form([
                        Forms\Components\Select::make('to_location_id')->label('To location')
                            ->options(fn () => Location::query()->pluck('location_name', 'id')->all())->searchable()->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (Box $record, array $data): void {
                        app(DocumentMovementService::class)->transferBox($record, (int) $data['to_location_id'], $data);
                        Notification::make()->title('Box transferred')->success()->send();
                    }),
                Tables\Actions\Action::make('moveOutBox')
                    ->label('Move Out')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->visible(fn (Box $record): bool => $record->status !== 'moved_out')
                    ->form([
                        Forms\Components\TextInput::make('destination')->label('External destination')->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (Box $record, array $data): void {
                        app(DocumentMovementService::class)->moveOutBox($record, $data['destination'], $data);
                        Notification::make()->title('Box moved out')->success()->send();
                    }),
                Tables\Actions\Action::make('returnBox')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (Box $record): bool => $record->status === 'moved_out')
                    ->form([
                        Forms\Components\Select::make('to_location_id')->label('Return to location')
                            ->options(fn () => Location::query()->pluck('location_name', 'id')->all())->searchable()->required(),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (Box $record, array $data): void {
                        app(DocumentMovementService::class)->returnBox($record, (int) $data['to_location_id'], $data);
                        Notification::make()->title('Box returned')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
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
