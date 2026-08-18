<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage users';

    /**
     * Platform-only roles. A non-platform user (e.g. Company Admin, who holds
     * `manage users` for their own tenant) must never be able to grant these
     * or flip is_platform_user — either would escalate the target account to
     * platform-wide read/write access across every tenant.
     */
    public const PLATFORM_ROLES = ['Datamation Super Admin', 'Datamation Management'];

    /**
     * Defense in depth against the roles Select above: removes any
     * platform-only role from $user unless the acting user is themselves a
     * platform user. Called after create/edit regardless of what the client
     * submitted, since relationship fields aren't covered by
     * mutateFormDataBeforeCreate/Save.
     */
    public static function stripDisallowedRoles(User $user): void
    {
        if (auth()->user()?->is_platform_user) {
            return;
        }

        $user->roles()
            ->whereIn('name', self::PLATFORM_ROLES)
            ->get()
            ->each(fn ($role) => $user->removeRole($role));
    }

    /**
     * Close the credential-takeover path this resource would otherwise leave
     * open: a platform user can share a tenant's customer_id (e.g. the
     * platform admin seeded by DatabaseSeeder), so BaseResource's
     * customer_id-based scoping alone would let a same-tenant Company Admin
     * open that platform user's edit page and set a new password —
     * full platform takeover, without ever touching is_platform_user or
     * roles. Deny every write action on a platform-user record to any actor
     * who is not themselves a platform user, regardless of customer_id.
     */
    public static function can(string|UnitEnum $action, ?Model $record = null): bool
    {
        if ($record instanceof User
            && $record->is_platform_user
            && ! auth()->user()?->is_platform_user
            && in_array($action, static::WRITE_ACTIONS, true)) {
            return false;
        }

        return parent::can($action, $record);
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
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank to keep the current password.'),
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
                Forms\Components\Toggle::make('is_platform_user')
                    ->visible(fn (): bool => (bool) auth()->user()?->is_platform_user),
                Forms\Components\Select::make('roles')->multiple()
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn ($query) => auth()->user()?->is_platform_user
                            ? $query
                            : $query->whereNotIn('name', self::PLATFORM_ROLES),
                    ),
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
        $actor = auth()->user();

        if (! $actor?->is_platform_user) {
            $data['is_platform_user'] = false;

            // User has no BelongsToCustomer scope, so nothing else forces
            // customer_id back to the acting tenant user's own company —
            // without this, a Company Admin could create a user under
            // another tenant by submitting a different customer_id.
            if ($actor?->customer_id) {
                $data['customer_id'] = $actor->customer_id;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        UserResource::stripDisallowedRoles($this->record);
    }
}

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Preserve, don't clobber: UserResource::can() already denies write
        // access to a platform-user record for a non-platform actor, so this
        // is unreachable in the normal flow. But forcing false here (rather
        // than the prior value) would silently downgrade an already-platform
        // account the moment any other field on it was edited — keep the
        // record's existing value instead of overwriting it.
        $actor = auth()->user();

        if (! $actor?->is_platform_user) {
            $data['is_platform_user'] = $this->record->is_platform_user;

            // Same tenant-hop guard as CreateUser::mutateFormDataBeforeCreate —
            // User has no BelongsToCustomer scope, so re-force customer_id on
            // every edit rather than trust whatever the form submitted.
            if ($actor?->customer_id) {
                $data['customer_id'] = $actor->customer_id;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        UserResource::stripDisallowedRoles($this->record);
    }
}
