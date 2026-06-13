<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentTypeResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\DocumentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentTypeResource extends BaseResource
{
    protected static ?string $model = DocumentType::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':documents'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage documents';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Document Tracking';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('type_code')->required()->maxLength(100),
                Forms\Components\TextInput::make('type_name')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTypes::route('/'),
            'create' => Pages\CreateDocumentType::route('/create'),
            'edit' => Pages\EditDocumentType::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\DocumentTypeResource\Pages;

use App\Filament\Resources\DocumentTypeResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListDocumentTypes extends ListRecords
{
    protected static string $resource = DocumentTypeResource::class;
}

class CreateDocumentType extends CreateRecord
{
    protected static string $resource = DocumentTypeResource::class;
}

class EditDocumentType extends EditRecord
{
    protected static string $resource = DocumentTypeResource::class;
}
