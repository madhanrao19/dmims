<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentFileResource\Pages;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\DocumentFile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentFileResource extends BaseResource
{
    protected static ?string $model = DocumentFile::class;

    protected static string|array $routeMiddleware = [EnsureModuleEnabled::class.':documents'];

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage documents';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Documents';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\BelongsToSelect::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('file_barcode')->required()->maxLength(150),
                Forms\Components\TextInput::make('file_reference_no')->maxLength(150),
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\BelongsToSelect::make('document_type_id')
                    ->relationship('documentType', 'type_name')
                    ->searchable(),
                Forms\Components\BelongsToSelect::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable(),
                Forms\Components\TextInput::make('owner_name')->maxLength(255),
                Forms\Components\BelongsToSelect::make('current_box_id')
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
                Forms\Components\Textarea::make('remarks')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_barcode')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('title')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('currentBox.box_number')->label('Box')->sortable(),
                Tables\Columns\TextColumn::make('current_status')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
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
