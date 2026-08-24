<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Clusters\MyCompany\Pages\Concerns\HasEmbeddedResourceTable;
use App\Filament\Resources\CustomerModuleResource;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class EnabledModules extends Page implements HasTable
{
    use HasEmbeddedResourceTable;
    use InteractsWithTable;

    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'Enabled Modules';

    protected static ?string $title = 'Enabled Modules';

    protected static function sourceResource(): string
    {
        return CustomerModuleResource::class;
    }

    public function table(Table $table): Table
    {
        return $this->embeddedResourceTable($table);
    }
}
