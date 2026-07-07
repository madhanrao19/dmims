<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentMovementLogResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\DocumentMovementLog;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentMovementLogResource extends BaseResource
{
    protected static ?string $model = DocumentMovementLog::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':document_tracking'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage documents';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Document Tracking';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::customerIdField(),
                Forms\Components\TextInput::make('movement_no')->maxLength(100),
                Forms\Components\TextInput::make('movable_type')->maxLength(150),
                Forms\Components\TextInput::make('movable_id')->maxLength(100),
                Forms\Components\TextInput::make('action_type')->maxLength(150),
                Forms\Components\Select::make('from_location_id')
                    ->relationship('fromLocation', 'location_name')
                    ->searchable(),
                Forms\Components\Select::make('to_location_id')
                    ->relationship('toLocation', 'location_name')
                    ->searchable(),
                Forms\Components\Select::make('from_box_id')
                    ->relationship('fromBox', 'box_number')
                    ->searchable(),
                Forms\Components\Select::make('to_box_id')
                    ->relationship('toBox', 'box_number')
                    ->searchable(),
                Forms\Components\TextInput::make('source_origin')->maxLength(255),
                Forms\Components\TextInput::make('destination')->maxLength(255),
                Forms\Components\TextInput::make('scanned_barcode')->maxLength(150),
                Forms\Components\Textarea::make('remarks')->rows(3),
                Forms\Components\TextInput::make('performed_by')->numeric(),
                Forms\Components\DateTimePicker::make('performed_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('action_type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('performed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('fromLocation.location_name')->label('From Location')->sortable(),
                Tables\Columns\TextColumn::make('toLocation.location_name')->label('To Location')->sortable(),
                Tables\Columns\TextColumn::make('fromBox.box_number')->label('From Box')->sortable(),
                Tables\Columns\TextColumn::make('toBox.box_number')->label('To Box')->sortable(),
            ])
            ->defaultSort('performed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentMovementLogs::route('/'),
            'create' => Pages\CreateDocumentMovementLog::route('/create'),
            'edit' => Pages\EditDocumentMovementLog::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\DocumentMovementLogResource\Pages;

use App\Filament\Resources\DocumentMovementLogResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListDocumentMovementLogs extends ListRecords
{
    protected static string $resource = DocumentMovementLogResource::class;
}

class CreateDocumentMovementLog extends CreateRecord
{
    protected static string $resource = DocumentMovementLogResource::class;
}

class EditDocumentMovementLog extends EditRecord
{
    protected static string $resource = DocumentMovementLogResource::class;
}
