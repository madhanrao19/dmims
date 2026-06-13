<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerSubscriptionResource\Pages;
use App\Models\CustomerSubscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerSubscriptionResource extends BaseResource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static bool $applyCustomerScope = true;

    protected static ?string $permission = 'manage subscriptions';

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('subscription_plan_id')
                    ->relationship('subscriptionPlan', 'plan_name')
                    ->searchable(),
                Forms\Components\TextInput::make('subscription_no')->required()->maxLength(100),
                Forms\Components\DatePicker::make('valid_from')->required(),
                Forms\Components\DatePicker::make('valid_to')->required(),
                Forms\Components\TextInput::make('grace_period_days')->numeric()->default(0),
                Forms\Components\TextInput::make('max_users')->numeric(),
                Forms\Components\TextInput::make('max_products')->numeric(),
                Forms\Components\TextInput::make('max_document_files')->numeric(),
                Forms\Components\TextInput::make('max_boxes')->numeric(),
                Forms\Components\Textarea::make('allowed_reports')->helperText('Enter JSON array of allowed report codes.'),
                Forms\Components\Textarea::make('enabled_modules')->helperText('Enter JSON array of enabled module codes.'),
                Forms\Components\TextInput::make('support_level')->maxLength(100),
                Forms\Components\Select::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'near_expiry' => 'Near Expiry',
                        'expired_grace' => 'Expired Grace',
                        'restricted' => 'Restricted',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('trial')
                    ->required(),
                Forms\Components\Textarea::make('renewal_notes')->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscription_no')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subscriptionPlan.plan_name')->label('Plan')->sortable(),
                Tables\Columns\TextColumn::make('valid_to')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerSubscriptions::route('/'),
            'create' => Pages\CreateCustomerSubscription::route('/create'),
            'edit' => Pages\EditCustomerSubscription::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListCustomerSubscriptions extends ListRecords
{
    protected static string $resource = CustomerSubscriptionResource::class;
}

class CreateCustomerSubscription extends CreateRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;
}

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;
}
