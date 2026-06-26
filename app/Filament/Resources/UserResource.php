<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage users';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'username', 'employee_id'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
                Forms\Components\TextInput::make('username')->maxLength(100),
                Forms\Components\TextInput::make('employee_id')->maxLength(100),
                Forms\Components\TextInput::make('phone')->maxLength(50),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable(),
                Forms\Components\Select::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable(),
                Forms\Components\TextInput::make('job_title')->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                        'locked' => 'Locked',
                        'password_expired' => 'Password Expired',
                        'archived' => 'Archived',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_platform_user'),
                Forms\Components\Select::make('roles')->multiple()
                    ->relationship('roles', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\IconColumn::make('is_platform_user')->boolean()->label('Platform User'),
                Tables\Columns\IconColumn::make('app_authentication_secret')
                    ->label('2FA')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->hasAppAuthenticationEnabled()),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                        'locked' => 'Locked',
                        'password_expired' => 'Password Expired',
                        'archived' => 'Archived',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
