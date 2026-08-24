<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Clusters\MyCompany\Pages\Concerns\HasEmbeddedResourceTable;
use App\Filament\Resources\BillingRecordResource;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Security & Access Control Matrix §9: "Own Billing" additionally requires
 * the Billing View module — enforced automatically here since canAccess()
 * and table() both delegate to BillingRecordResource, whose
 * moduleEnabledForUser() already checks its billing_view route middleware.
 */
class Billing extends Page implements HasTable
{
    use HasEmbeddedResourceTable;
    use InteractsWithTable;

    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'Billing';

    protected static ?string $title = 'Billing';

    protected static function sourceResource(): string
    {
        return BillingRecordResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->embeddedResourceTable($table);
    }
}
