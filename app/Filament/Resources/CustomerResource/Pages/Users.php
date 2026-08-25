<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\HasCustomerScopedEmbeddedTable;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Platform Customer 360 tab: this customer's users, embedding
 * UserResource's own table constrained to the selected customer — see
 * HasCustomerScopedEmbeddedTable for why the scoping must be explicit here
 * (unlike My Company's equivalent tab).
 */
class Users extends Page implements HasTable
{
    use HasCustomerScopedEmbeddedTable;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $title = 'Customer Users';

    public static function canAccess(array $parameters = []): bool
    {
        return CustomerResource::canAccessCustomer360($parameters['record'] ?? null);
    }

    protected static function sourceResource(): string
    {
        return UserResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->customerScopedResourceTable($table)
            ->headerActions([
                // Mirrors UserResource\Pages\CreateUser::afterCreate() —
                // enforcePlatformRoleConsistency() in particular is not
                // optional: without it a role picked in this modal could
                // leave is_platform_user out of sync with the user's actual
                // roles (BaseResource::can()/shouldRegisterNavigation() key
                // off is_platform_user alone).
                $this->customerScopedCreateAction('Add User')
                    ->after(function (User $record): void {
                        UserResource::stripDisallowedRoles($record);
                        UserResource::enforcePlatformRoleConsistency($record);
                    }),
            ]);
    }
}
