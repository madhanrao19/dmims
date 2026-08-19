<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerResource extends BaseResource
{
    protected static ?string $model = Customer::class;

    protected static ?string $permission = 'manage customers';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    /**
     * Security & Access Control Matrix §5: Company Admin/Supervisor get
     * "View Customers: Own Company" — read-only access to their own
     * company's record. Customer IS the tenant entity (no customer_id
     * column of its own), so this can't use BaseResource's standard
     * $applyCustomerScope mechanism — scope by primary key instead.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->is_platform_user && $user->customer_id) {
            return $query->whereKey($user->customer_id);
        }

        return $query;
    }

    /** Same reasoning as getEloquentQuery(): deny direct-URL access to a
     *  different company's record, since BaseResource's generic customer_id
     *  record check doesn't apply to the Customer model itself. */
    public static function can(string|UnitEnum $action, ?Model $record = null): bool
    {
        $user = auth()->user();

        if ($record instanceof Customer
            && $user && ! $user->is_platform_user
            && (int) $record->getKey() !== (int) $user->customer_id) {
            return false;
        }

        return parent::can($action, $record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('company_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('company_code')->required()->maxLength(50)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('contact_person')->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('phone')->maxLength(50),
                Forms\Components\Textarea::make('address')->rows(3),
                Forms\Components\Select::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'near_expiry' => 'Near Expiry',
                        'expired' => 'Expired',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                        'archived' => 'Archived',
                    ])
                    ->default('trial')
                    ->required(),
                Forms\Components\Textarea::make('notes')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('company_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('phone')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'near_expiry' => 'Near Expiry',
                        'expired' => 'Expired',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                        'archived' => 'Archived',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\CreateRecord;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
