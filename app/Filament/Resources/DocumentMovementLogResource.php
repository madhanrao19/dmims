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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Document Tracking';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => auth()->user()?->is_platform_user ? null : auth()->user()?->customer_id)
                    ->required()
                    ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user),
                Forms\Components\TextInput::make('movement_no')->maxLength(100),
                Forms\Components\TextInput::make('movable_type')->maxLength(150),
                Forms\Components\TextInput::make('movable_id')->maxLength(100),
                Forms\Components\TextInput::make('action_type')->maxLength(150),
                Forms\Components\Select::make('from_location_id')
                    ->label('From Location')
                    ->relationship('fromLocation', 'location_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('to_location_id')
                    ->label('To Location')
                    ->relationship('toLocation', 'location_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('from_box_id')
                    ->label('From Box')
                    ->relationship('fromBox', 'box_number')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('to_box_id')
                    ->label('To Box')
                    ->relationship('toBox', 'box_number')
                    ->searchable()
                    ->preload(),
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
                Tables\Columns\TextColumn::make('performed_at')->label('Date & Time')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('action_type')->label('Movement Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('fromLocation.location_name')->label('From Location')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('fromBox.box_number')->label('From Box')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('toLocation.location_name')->label('To Location')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('toBox.box_number')->label('To Box')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('destination')->label('Destination')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('performedBy.name')->label('Operator')->sortable(),
                Tables\Columns\TextColumn::make('movement_no')->label('Reference / Tracking No')->sortable()->searchable(),
            ])
            ->defaultSort('performed_at', 'desc');
    }

    public static function getPages(): array
    {
        // List-only: movement rows are written exclusively by
        // DocumentMovementService, never through this admin form — a manual
        // create/edit here would let a user fabricate or backdate chain-of-
        // custody history.
        return [
            'index' => Pages\ListDocumentMovementLogs::route('/'),
        ];
    }
}

namespace App\Filament\Resources\DocumentMovementLogResource\Pages;

use App\Filament\Resources\DocumentMovementLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentMovementLogs extends ListRecords
{
    protected static string $resource = DocumentMovementLogResource::class;
}
