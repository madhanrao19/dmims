<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Models\Module;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class ModuleResource extends BaseResource
{
    protected static ?string $model = Module::class;

    protected static ?string $permission = 'manage modules';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('module_code')->required()->maxLength(100),
                Forms\Components\TextInput::make('module_name')->required()->maxLength(255),
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
                Tables\Columns\TextColumn::make('module_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('module_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\ModuleResource\Pages;

use App\Filament\Resources\ModuleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListModules extends ListRecords
{
    protected static string $resource = ModuleResource::class;
}

class CreateModule extends CreateRecord
{
    protected static string $resource = ModuleResource::class;
}

class EditModule extends EditRecord
{
    protected static string $resource = ModuleResource::class;
}
