<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage users';

    /**
     * Roles that only platform staff may hold or assign. A tenant user with
     * `manage users` must never be able to grant these (they bypass every
     * access-control layer), nor promote anyone to a platform account.
     */
    public const PLATFORM_ROLES = ['Datamation Super Admin', 'Datamation Management'];

    /**
     * Force tenant-owned values on write so the privileged fields can never be
     * trusted from the request. Platform users keep whatever they submit.
     */
    public static function enforceTenantUserData(array $data): array
    {
        $actor = auth()->user();

        if ($actor && ! $actor->is_platform_user) {
            $data['customer_id'] = $actor->customer_id;
            $data['is_platform_user'] = false;
        }

        return $data;
    }

    /**
     * Restrict the assignable role list to non-platform roles for tenant users.
     */
    public static function assignableRoleQuery(Builder $query): Builder
    {
        if (! auth()->user()?->is_platform_user) {
            $query->whereNotIn('name', self::PLATFORM_ROLES);
        }

        return $query;
    }

    /**
     * Belt-and-suspenders: detach any platform role that reached a tenant-owned
     * user despite the scoped option list (e.g. a crafted request).
     */
    public static function stripPlatformRoles(User $user): void
    {
        if (auth()->user()?->is_platform_user) {
            return;
        }

        $platformRoleIds = $user->roles()->whereIn('name', self::PLATFORM_ROLES)->pluck('id');

        if ($platformRoleIds->isNotEmpty()) {
            $user->roles()->detach($platformRoleIds);
        }
    }

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
                // Platform-only: a tenant user must not choose an arbitrary
                // customer. Hidden and non-dehydrated for tenants; the owning
                // customer is forced server-side in the Create/Edit pages.
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user)
                    ->dehydrated(fn (): bool => (bool) auth()->user()?->is_platform_user),
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
                // Platform-only: granting a platform account bypasses every
                // access-control layer, so tenants can neither see nor submit it.
                Forms\Components\Toggle::make('is_platform_user')
                    ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user)
                    ->dehydrated(fn (): bool => (bool) auth()->user()?->is_platform_user),
                // Tenants may assign roles, but never the platform-staff roles.
                Forms\Components\Select::make('roles')->multiple()
                    ->relationship('roles', 'name', fn (Builder $query): Builder => static::assignableRoleQuery($query)),
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return UserResource::enforceTenantUserData($data);
    }

    protected function afterCreate(): void
    {
        UserResource::stripPlatformRoles($this->record);
    }
}

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return UserResource::enforceTenantUserData($data);
    }

    protected function afterSave(): void
    {
        UserResource::stripPlatformRoles($this->record);
    }
}
