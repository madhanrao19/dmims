<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Page;
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

    // Security & Access Control Matrix §5: customer users reach their own
    // company via the My Company > Profile tab instead of a standalone
    // "Customers" nav entry (which would otherwise show for Company Admin/
    // Supervisor, who both hold `view customers`).
    protected static bool $customerFacingViaMyCompany = true;

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

    /**
     * Platform Customer 360 (docs/CONFORMANCE_GAP_ANALYSIS.md, 25 Aug 2026
     * design review): "Customer roles must not access Platform Customer
     * 360." can('view', $record) above legitimately returns true for a
     * non-platform user viewing THEIR OWN customer record — My Company's
     * Overview page (App\Filament\Clusters\MyCompany\Pages\Overview)
     * depends on exactly that staying true, so it can't be tightened here.
     * Every Customer 360 page instead layers this additional
     * platform-user-only check in front of can('view').
     */
    public static function canAccessCustomer360(?Model $record = null): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->is_platform_user) && static::can('view', $record);
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

    /**
     * Platform Customer 360 Overview tab. Unlike My Company's Overview
     * (App\Filament\Clusters\MyCompany\Pages\Overview), which whitelists
     * fields to keep internal notes out of a TENANT's own browser bundle,
     * this is a platform-internal admin view of another company — `notes`
     * is legitimate platform-staff content here, so every form field is
     * shown.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('company_name'),
                TextEntry::make('company_code'),
                TextEntry::make('contact_person'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('address'),
                TextEntry::make('status'),
                TextEntry::make('notes'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('company_code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'near_expiry' => 'warning',
                        'expired', 'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
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
            // Platform Customer 360: selecting a customer opens Customer
            // 360 (the 'view' page) rather than nothing; Edit stays
            // directly reachable as its own row action.
            ->recordUrl(fn (Customer $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Platform Customer 360 (docs/CONFORMANCE_GAP_ANALYSIS.md, 25 Aug 2026
     * design review): the tab bar shown on every Customer record page.
     * Filament resolves each tab's shouldRegisterNavigation()/canAccess()
     * with ['record' => $customer] — see canAccessCustomer360() above,
     * which every tab page's canAccess() delegates to.
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewCustomer::class,
            Pages\Users::class,
            Pages\Modules::class,
            Pages\Subscription::class,
            Pages\License::class,
            Pages\BillingAndPayments::class,
            Pages\Locations::class,
            Pages\AuditLogs::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'users' => Pages\Users::route('/{record}/users'),
            'modules' => Pages\Modules::route('/{record}/modules'),
            'subscription' => Pages\Subscription::route('/{record}/subscription'),
            'license' => Pages\License::route('/{record}/license'),
            'billing' => Pages\BillingAndPayments::route('/{record}/billing'),
            'locations' => Pages\Locations::route('/{record}/locations'),
            'audit-logs' => Pages\AuditLogs::route('/{record}/audit-logs'),
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
