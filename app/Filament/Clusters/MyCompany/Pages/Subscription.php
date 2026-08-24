<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Clusters\MyCompany\Pages\Concerns\HasEmbeddedResourceTable;
use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Subscription extends Page implements HasTable
{
    use HasEmbeddedResourceTable;
    use InteractsWithTable;

    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'Subscription';

    protected static ?string $title = 'Subscription';

    protected static function sourceResource(): string
    {
        return CustomerSubscriptionResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->embeddedResourceTable($table);
    }
}
