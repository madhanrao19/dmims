<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends BaseResource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $permission = 'manage subscriptions';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('plan_code')->required()->maxLength(100),
                Forms\Components\TextInput::make('plan_name')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3),
                Forms\Components\TextInput::make('max_users')->numeric(),
                Forms\Components\TextInput::make('max_products')->numeric(),
                Forms\Components\TextInput::make('max_document_files')->numeric(),
                Forms\Components\TextInput::make('max_boxes')->numeric(),
                Forms\Components\Textarea::make('allowed_reports')->helperText('Enter JSON or leave blank for no restrictions.'),
                Forms\Components\Textarea::make('enabled_modules')->helperText('Enter JSON array of enabled module codes or leave blank.'),
                Forms\Components\TextInput::make('support_level')->maxLength(100),
                Forms\Components\TextInput::make('price')->numeric()->step('0.01'),
                Forms\Components\Select::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                        'custom' => 'Custom',
                    ])
                    ->default('yearly')
                    ->required(),
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
                Tables\Columns\TextColumn::make('plan_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('plan_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('billing_cycle')->sortable(),
                Tables\Columns\TextColumn::make('price')->money('usd')->sortable(),
                Tables\Columns\TextColumn::make('status')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\SubscriptionPlanResource\Pages;

use App\Filament\Resources\SubscriptionPlanResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPlans extends ListRecords
{
    protected static string $resource = SubscriptionPlanResource::class;
}

class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;
}

class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;
}
